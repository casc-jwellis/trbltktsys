<?php
/** @var array $groups */
?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">New Group</div>
            <div class="card-body p-4">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_group">
                    <div class="mb-3">
                        <label class="form-label" for="group_name">Group Name</label>
                        <input class="form-control" id="group_name" name="name" required maxlength="60">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="group_description">Description</label>
                        <textarea class="form-control" id="group_description" name="description" rows="2" maxlength="255"></textarea>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Create Group</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Members</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$groups): ?>
                            <tr><td colspan="4" class="text-center text-body-secondary py-4">No groups yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($groups as $group): ?>
                            <tr>
                                <td><?= e($group['name']) ?></td>
                                <td><?= e($group['description'] ?: '—') ?></td>
                                <td><?= (int) $group['member_count'] ?></td>
                                <td class="text-end">
                                    <form method="post" class="d-inline" onsubmit="return confirm('Remove this group? Members will be unassigned from it.');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_group">
                                        <input type="hidden" name="group_id" value="<?= (int) $group['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
