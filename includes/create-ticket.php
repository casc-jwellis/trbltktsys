<?php

// For an agent to open a ticket on a requester's behalf (a phone call, a
// walk-up) -- the logged-in counterpart to the public submit-ticket.php,
// which this deliberately mirrors. Unlike that form, the agent also chooses
// the ticket's status and who it's assigned to. The form itself is a modal
// on the ticket queue (includes/create-ticket-modal.php); dashboard.php
// calls these when it receives the modal's POST.

require_once __DIR__ . '/mail.php';

/** A blank create-ticket form's values. */
function create_ticket_defaults(): array
{
    return [
        'requester_name'   => '',
        'requester_email'  => '',
        'requester_phone'  => '',
        'subject'          => '',
        'description'      => '',
        'category'         => 'General',
        'priority'         => 'Medium',
        'status'           => 'Open',
        'assigned_agent_id' => 0,
        'assigned_groups'  => [],
    ];
}

/** The create-ticket form's values as the agent submitted them, so a failed attempt can be redisplayed. */
function create_ticket_form_from_post(array $post): array
{
    $form = create_ticket_defaults();
    foreach (array_keys($form) as $field) {
        if ($field === 'assigned_agent_id') {
            $form[$field] = (int) ($post[$field] ?? 0);
        } elseif ($field === 'assigned_groups') {
            $form[$field] = valid_ids_from_post((array) ($post[$field] ?? []), assignable_groups());
        } else {
            $form[$field] = trim((string) ($post[$field] ?? ''));
        }
    }
    return $form;
}

/**
 * Validates $form and, if it's good, creates the ticket and sends the
 * emails. Returns ['errors' => string[], 'ticket_id' => ?int] -- errors is
 * empty exactly when ticket_id is set.
 */
function create_ticket_submit(array $form, array $files): array
{
    $errors = [];

    if ($form['requester_name'] === '') {
        $errors[] = 'Please enter the requester\'s name.';
    }
    if (!filter_var($form['requester_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address for the requester.';
    }
    if (strlen($form['requester_phone']) > 30) {
        $errors[] = 'Phone number is too long (30 characters max).';
    }
    if ($form['subject'] === '') {
        $errors[] = 'Please enter a subject.';
    }
    if ($form['description'] === '') {
        $errors[] = 'Please describe the issue.';
    }
    if (!in_array($form['category'], category_names(), true)) {
        $errors[] = 'Please choose a valid category.';
    }
    if (!in_array($form['priority'], TICKET_PRIORITIES, true)) {
        $errors[] = 'Please choose a valid priority.';
    }
    if (!in_array($form['status'], TICKET_STATUSES, true)) {
        $errors[] = 'Please choose a valid status.';
    }

    $assignedAgentId = null;
    if (ticket_assigned_agent_supported()
        && in_array($form['assigned_agent_id'], array_map('intval', array_column(active_agents(), 'id')), true)) {
        $assignedAgentId = $form['assigned_agent_id'];
    }

    $attachmentPath = null;
    if (!$errors) {
        try {
            $attachmentPath = store_ticket_attachment($files['screenshot'] ?? []);
        } catch (InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if ($errors) {
        return ['errors' => $errors, 'ticket_id' => null];
    }

    $creatorId = current_user_id();
    $phone = $form['requester_phone'] !== '' ? $form['requester_phone'] : null;

    db()->beginTransaction();

    $stmt = db()->prepare(
        'INSERT INTO requesters (email, name, phone) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE name = VALUES(name), phone = COALESCE(VALUES(phone), phone)'
    );
    $stmt->execute([$form['requester_email'], $form['requester_name'], $phone]);

    $ticketValues = [
        $form['requester_name'],
        $form['requester_email'],
        $form['subject'],
        $form['description'],
        $form['category'],
        $form['priority'],
        $form['status'],
        $attachmentPath,
    ];
    $ticketSql = 'INSERT INTO tickets (requester_name, requester_email, subject, description, category, priority, status, attachment_path';

    // Each of these columns may not exist yet on a database that's pending
    // migrate.php -- degrade gracefully rather than fail.
    if (ticket_assigned_agent_supported()) {
        $ticketSql .= ', assigned_agent_id';
        $ticketValues[] = $assignedAgentId;
    }
    if (ticket_created_by_supported()) {
        $ticketSql .= ', created_by_user_id';
        $ticketValues[] = $creatorId;
    }
    $publicToken = null;
    if (ticket_public_tokens_supported()) {
        $publicToken = bin2hex(random_bytes(32));
        $ticketSql .= ', public_token';
        $ticketValues[] = $publicToken;
    }

    $stmt = db()->prepare($ticketSql . ') VALUES (' . implode(', ', array_fill(0, count($ticketValues), '?')) . ')');
    $stmt->execute($ticketValues);
    $ticketId = (int) db()->lastInsertId();

    // Groups the agent picked win; otherwise fall back to whichever groups
    // handle the category, same as a self-submitted ticket.
    if ($form['assigned_groups']) {
        save_ticket_assignments($ticketId, $form['assigned_groups']);
    } else {
        assign_ticket_by_category($ticketId, $form['category']);
    }

    db()->commit();

    // An agent who assigns the ticket to themselves obviously knows about it
    // already, so it shouldn't show as "New" for them.
    if ($assignedAgentId === $creatorId) {
        mark_ticket_viewed($ticketId);
    }

    $creatorName = current_user()['full_name'];

    if ($publicToken !== null) {
        try {
            send_ticket_confirmation_email([
                'id'              => $ticketId,
                'subject'         => $form['subject'],
                'status'          => $form['status'],
                'requester_name'  => $form['requester_name'],
                'requester_email' => $form['requester_email'],
                'public_token'    => $publicToken,
                'created_by_name' => $creatorName,
            ]);
        } catch (Throwable $e) {
            // Don't let a mail failure block ticket creation -- the ticket
            // itself is already committed. Just leave a trail for an admin.
            error_log('Failed to send ticket confirmation email for ticket #' . $ticketId . ': ' . $e->getMessage());
        }
    }

    $stmt = db()->prepare('SELECT email FROM users WHERE id = ?');
    $stmt->execute([$creatorId]);
    $creatorEmail = $stmt->fetchColumn() ?: null;

    send_new_ticket_notification($ticketId, $form['subject'], $form['priority'], $creatorName, $creatorEmail, $form['status']);

    return ['errors' => [], 'ticket_id' => $ticketId];
}
