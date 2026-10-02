<?php
/**
 * The "+ New Ticket" modal on the ticket queue.
 *
 * @var array    $createForm   the form's current values (see create_ticket_defaults())
 * @var string[] $createErrors validation errors from a failed attempt; the modal reopens itself to show them
 */
$createGroups = ticket_assignments_supported() ? assignable_groups() : [];
$createAgents = ticket_assigned_agent_supported() ? active_agents() : [];
?>
<div class="modal fade" id="createTicketModal" tabindex="-1" aria-labelledby="createTicketModalLabel" aria-hidden="true" <?= $createErrors ? 'data-show-on-load' : '' ?>>
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <?php // The form is the .modal-content itself: wrapping it around a .modal-content breaks the scrollable body's height. ?>
        <form class="modal-content" method="post" enctype="multipart/form-data" novalidate data-loading-text="Creating...">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_ticket">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="createTicketModalLabel">Create Ticket</h5>
                        <div class="small text-body-secondary">Open a ticket on someone's behalf. They'll be emailed a confirmation with a link to follow it.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if ($createErrors): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($createErrors as $createError): ?>
                                    <li><?= e($createError) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <p class="small text-body-secondary"><span class="required-key"></span>Required</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required" for="new_requester_name">Requester Name</label>
                            <input class="form-control" id="new_requester_name" name="requester_name" required value="<?= e($createForm['requester_name']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required" for="new_requester_email">Requester Email</label>
                            <input type="email" class="form-control" id="new_requester_email" name="requester_email" required value="<?= e($createForm['requester_email']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="new_requester_phone">Requester Phone</label>
                            <input class="form-control" id="new_requester_phone" name="requester_phone" maxlength="30" value="<?= e($createForm['requester_phone']) ?>">
                        </div>
                        <div class="w-100"></div>
                        <div class="col-md-4">
                            <label class="form-label required" for="new_category">Category</label>
                            <select class="form-select" id="new_category" name="category">
                                <?php foreach (category_names() as $category): ?>
                                    <option value="<?= e($category) ?>" <?= $createForm['category'] === $category ? 'selected' : '' ?>><?= e($category) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required" for="new_priority">Priority</label>
                            <select class="form-select" id="new_priority" name="priority">
                                <?php foreach (TICKET_PRIORITIES as $priority): ?>
                                    <option value="<?= e($priority) ?>" <?= $createForm['priority'] === $priority ? 'selected' : '' ?>><?= e($priority) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required" for="new_status">Status</label>
                            <select class="form-select" id="new_status" name="status">
                                <?php foreach (TICKET_STATUSES as $status): ?>
                                    <option value="<?= e($status) ?>" <?= $createForm['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label required" for="new_subject">Subject</label>
                            <input class="form-control" id="new_subject" name="subject" required value="<?= e($createForm['subject']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label required" for="new_description">Describe the Issue</label>
                            <textarea class="form-control" id="new_description" name="description" rows="5" required><?= e($createForm['description']) ?></textarea>
                        </div>
                        <?php if (ticket_assigned_agent_supported()): ?>
                            <div class="col-12">
                                <label class="form-label" for="new_assigned_agent_id">Assigned Agent</label>
                                <select class="form-select" id="new_assigned_agent_id" name="assigned_agent_id">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($createAgents as $agent): ?>
                                        <option value="<?= (int) $agent['id'] ?>" <?= $createForm['assigned_agent_id'] === (int) $agent['id'] ? 'selected' : '' ?>><?= e($agent['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Once an agent is assigned, ticket emails go only to them instead of the whole group.</div>
                            </div>
                        <?php endif; ?>
                        <?php if ($createGroups): ?>
                            <div class="col-12">
                                <label class="form-label">Assigned Groups</label>
                                <div class="assignment-picker">
                                    <div class="assignment-pills mb-2"></div>
                                    <input type="text" class="form-control form-control-sm assignment-search" placeholder="Search groups...">
                                    <div class="list-group assignment-dropdown"></div>
                                    <div class="assignment-options">
                                        <?php foreach ($createGroups as $group): ?>
                                            <div class="form-check assignment-option">
                                                <input class="form-check-input" type="checkbox" name="assigned_groups[]" value="<?= (int) $group['id'] ?>" id="new_group_<?= (int) $group['id'] ?>" <?= in_array((int) $group['id'], $createForm['assigned_groups'], true) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="new_group_<?= (int) $group['id'] ?>"><?= e($group['name']) ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="form-text">Leave empty to assign by category, the same as a ticket the requester submits themselves.</div>
                            </div>
                        <?php endif; ?>
                        <div class="col-12">
                            <label class="form-label" for="new_screenshot">Screenshot (optional)</label>
                            <input type="file" class="form-control" id="new_screenshot" name="screenshot" accept="image/png,image/jpeg,image/gif,image/webp">
                            <div class="form-text">PNG, JPEG, GIF, or WEBP. 5 MB max.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Ticket</button>
                </div>
        </form>
    </div>
</div>
