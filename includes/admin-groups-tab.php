<?php
/**
 * @var array $groups
 * @var array $allCategories
 * @var array $groupCategoryMap
 */
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
                            <th>Categories</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$groups): ?>
                            <tr><td colspan="5" class="text-center text-body-secondary py-4">No groups yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($groups as $group): ?>
                            <tr>
                                <td><?= e($group['name']) ?></td>
                                <td><?= e($group['description'] ?: '—') ?></td>
                                <td><?= (int) $group['member_count'] ?></td>
                                <td><?= e($group['category_names'] ?: '—') ?></td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                data-bs-toggle="modal" data-bs-target="#editGroupModal<?= (int) $group['id'] ?>">
                                            Edit
                                        </button>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Remove this group? Members will be unassigned from it.');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_group">
                                            <input type="hidden" name="group_id" value="<?= (int) $group['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php foreach ($groups as $group): ?>
    <?php
    $gid = (int) $group['id'];
    $groupCategoryIds = $groupCategoryMap[$gid] ?? [];
    ?>
    <div class="modal fade" id="editGroupModal<?= $gid ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_group">
                    <input type="hidden" name="group_id" value="<?= $gid ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit <?= e($group['name']) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="edit_group_name_<?= $gid ?>">Group Name</label>
                            <input class="form-control" id="edit_group_name_<?= $gid ?>" name="name" required maxlength="60" value="<?= e($group['name']) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="edit_group_description_<?= $gid ?>">Description</label>
                            <textarea class="form-control" id="edit_group_description_<?= $gid ?>" name="description" rows="2" maxlength="255"><?= e($group['description']) ?></textarea>
                        </div>
                        <div class="mb-2">
                            <label class="form-label d-block">Categories</label>
                            <?php if (!$allCategories): ?>
                                <p class="text-body-secondary small mb-0">No categories yet — create one on the Categories tab.</p>
                            <?php endif; ?>
                            <?php foreach ($allCategories as $category): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="categories[]"
                                           value="<?= (int) $category['id'] ?>" id="edit_group_category_<?= $gid ?>_<?= (int) $category['id'] ?>"
                                           <?= in_array((int) $category['id'], $groupCategoryIds, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="edit_group_category_<?= $gid ?>_<?= (int) $category['id'] ?>"><?= e($category['name']) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>
