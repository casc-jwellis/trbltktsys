<?php
/**
 * @var array $users
 * @var array $allGroups
 * @var array $allCategories
 * @var array $userGroupMap
 * @var array $userCategoryMap
 */
$myId = current_user_id();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-body-secondary mb-0"><?= count($users) ?> user<?= count($users) === 1 ? '' : 's' ?></p>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
        + New User
    </button>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Admin</th>
                    <th>Groups</th>
                    <th>Categories</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$users): ?>
                    <tr><td colspan="9" class="text-center text-body-secondary py-4">No users found.</td></tr>
                <?php endif; ?>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= e($user['full_name']) ?></td>
                        <td><?= e($user['username']) ?></td>
                        <td><?= $user['email'] ? '<a href="mailto:' . e($user['email']) . '">' . e($user['email']) . '</a>' : '—' ?></td>
                        <td><?= e($user['phone'] ?: '—') ?></td>
                        <td><?= (int) $user['is_admin'] === 1 ? '<span class="badge text-bg-primary">Administrator</span>' : '—' ?></td>
                        <td><?= e($user['group_names'] ?: '—') ?></td>
                        <td><?= e($user['category_names'] ?: '—') ?></td>
                        <td>
                            <?php if ((int) $user['is_locked'] === 1): ?>
                                <span class="badge text-bg-secondary">Locked</span>
                            <?php else: ?>
                                <span class="badge text-bg-success">Active</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="modal" data-bs-target="#editUserModal<?= (int) $user['id'] ?>">
                                    Edit
                                </button>

                                <form method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_lock">
                                    <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                    <input type="hidden" name="lock" value="<?= (int) $user['is_locked'] === 1 ? '0' : '1' ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-warning"
                                            <?= (int) $user['id'] === $myId ? 'disabled' : '' ?>>
                                        <?= (int) $user['is_locked'] === 1 ? 'Unlock' : 'Lock' ?>
                                    </button>
                                </form>

                                <form method="post" class="d-inline" onsubmit="return confirm('Remove this user? This cannot be undone.');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                            <?= (int) $user['id'] === $myId ? 'disabled' : '' ?>>
                                        Remove
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_user">
                <div class="modal-header">
                    <h5 class="modal-title">New Helpdesk User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="new_username">Username</label>
                        <input class="form-control" id="new_username" name="username" required maxlength="50">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="new_full_name">Full Name</label>
                        <input class="form-control" id="new_full_name" name="full_name" required maxlength="100">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="new_email">Email</label>
                            <input type="email" class="form-control" id="new_email" name="email" required maxlength="150">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="new_phone">Phone <span class="text-body-secondary">(optional)</span></label>
                            <input class="form-control" id="new_phone" name="phone" maxlength="30">
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="new_password">Password</label>
                            <input type="password" class="form-control" id="new_password" name="password" required minlength="8">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="new_password_confirm">Confirm Password</label>
                            <input type="password" class="form-control" id="new_password_confirm" name="password_confirm" required minlength="8">
                        </div>
                    </div>
                    <div class="mb-3 mt-3 form-check">
                        <input class="form-check-input" type="checkbox" name="is_admin" value="1" id="new_is_admin">
                        <label class="form-check-label" for="new_is_admin">Administrator</label>
                        <div class="form-text">Grants full access to Admin Settings. Every account can already manage tickets.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label d-block">Groups</label>
                        <?php if (!$allGroups): ?>
                            <p class="text-body-secondary small mb-0">No groups yet — create one on the Groups tab.</p>
                        <?php else: ?>
                            <div class="assignment-picker">
                                <div class="assignment-pills mb-2"></div>
                                <input type="text" class="form-control form-control-sm assignment-search" placeholder="Search groups...">
                                <div class="list-group assignment-dropdown"></div>
                                <div class="assignment-options">
                                    <?php foreach ($allGroups as $group): ?>
                                        <div class="form-check assignment-option">
                                            <input class="form-check-input" type="checkbox" name="groups[]"
                                                   value="<?= (int) $group['id'] ?>" id="new_group_<?= (int) $group['id'] ?>">
                                            <label class="form-check-label" for="new_group_<?= (int) $group['id'] ?>"><?= e($group['name']) ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-2">
                        <label class="form-label d-block">Categories</label>
                        <?php if (!$allCategories): ?>
                            <p class="text-body-secondary small mb-0">No categories yet — create one on the Categories tab.</p>
                        <?php else: ?>
                            <div class="assignment-picker">
                                <div class="assignment-pills mb-2"></div>
                                <input type="text" class="form-control form-control-sm assignment-search" placeholder="Search categories...">
                                <div class="list-group assignment-dropdown"></div>
                                <div class="assignment-options">
                                    <?php foreach ($allCategories as $category): ?>
                                        <div class="form-check assignment-option">
                                            <input class="form-check-input" type="checkbox" name="categories[]"
                                                   value="<?= (int) $category['id'] ?>" id="new_category_<?= (int) $category['id'] ?>">
                                            <label class="form-check-label" for="new_category_<?= (int) $category['id'] ?>"><?= e($category['name']) ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($users as $user): ?>
    <?php
    $uid = (int) $user['id'];
    $userGroupIds = $userGroupMap[$uid] ?? [];
    $userCategoryIds = $userCategoryMap[$uid] ?? [];
    ?>
    <div class="modal fade" id="editUserModal<?= $uid ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" value="<?= $uid ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit <?= e($user['full_name']) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="edit_username_<?= $uid ?>">Username</label>
                            <input class="form-control" id="edit_username_<?= $uid ?>" name="username" required maxlength="50" value="<?= e($user['username']) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="edit_full_name_<?= $uid ?>">Full Name</label>
                            <input class="form-control" id="edit_full_name_<?= $uid ?>" name="full_name" required maxlength="100" value="<?= e($user['full_name']) ?>">
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label" for="edit_email_<?= $uid ?>">Email</label>
                                <input type="email" class="form-control" id="edit_email_<?= $uid ?>" name="email" required maxlength="150" value="<?= e($user['email']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="edit_phone_<?= $uid ?>">Phone <span class="text-body-secondary">(optional)</span></label>
                                <input class="form-control" id="edit_phone_<?= $uid ?>" name="phone" maxlength="30" value="<?= e($user['phone']) ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="edit_password_<?= $uid ?>">New Password</label>
                            <input type="password" class="form-control" id="edit_password_<?= $uid ?>" name="new_password" minlength="8">
                            <div class="form-text">Leave blank to keep the current password.</div>
                        </div>
                        <div class="mb-3 form-check">
                            <input class="form-check-input" type="checkbox" name="is_admin" value="1" id="edit_is_admin_<?= $uid ?>"
                                   <?= (int) $user['is_admin'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="edit_is_admin_<?= $uid ?>">Administrator</label>
                            <div class="form-text">Grants full access to Admin Settings. Every account can already manage tickets.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label d-block">Groups</label>
                            <?php if (!$allGroups): ?>
                                <p class="text-body-secondary small mb-0">No groups yet — create one on the Groups tab.</p>
                            <?php else: ?>
                                <div class="assignment-picker">
                                    <div class="assignment-pills mb-2"></div>
                                    <input type="text" class="form-control form-control-sm assignment-search" placeholder="Search groups...">
                                    <div class="list-group assignment-dropdown"></div>
                                    <div class="assignment-options">
                                        <?php foreach ($allGroups as $group): ?>
                                            <div class="form-check assignment-option">
                                                <input class="form-check-input" type="checkbox" name="groups[]"
                                                       value="<?= (int) $group['id'] ?>" id="edit_group_<?= $uid ?>_<?= (int) $group['id'] ?>"
                                                       <?= in_array((int) $group['id'], $userGroupIds, true) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="edit_group_<?= $uid ?>_<?= (int) $group['id'] ?>"><?= e($group['name']) ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="mb-2">
                            <label class="form-label d-block">Categories</label>
                            <?php if (!$allCategories): ?>
                                <p class="text-body-secondary small mb-0">No categories yet — create one on the Categories tab.</p>
                            <?php else: ?>
                                <div class="assignment-picker">
                                    <div class="assignment-pills mb-2"></div>
                                    <input type="text" class="form-control form-control-sm assignment-search" placeholder="Search categories...">
                                    <div class="list-group assignment-dropdown"></div>
                                    <div class="assignment-options">
                                        <?php foreach ($allCategories as $category): ?>
                                            <div class="form-check assignment-option">
                                                <input class="form-check-input" type="checkbox" name="categories[]"
                                                       value="<?= (int) $category['id'] ?>" id="edit_category_<?= $uid ?>_<?= (int) $category['id'] ?>"
                                                       <?= in_array((int) $category['id'], $userCategoryIds, true) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="edit_category_<?= $uid ?>_<?= (int) $category['id'] ?>"><?= e($category['name']) ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
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
