<?php
/** @var array $categories */
?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">New Category</div>
            <div class="card-body p-4">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_category">
                    <div class="mb-3">
                        <label class="form-label" for="category_name">Category Name</label>
                        <input class="form-control" id="category_name" name="name" required maxlength="50">
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Create Category</button>
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
                            <th>Tickets</th>
                            <th>Groups</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$categories): ?>
                            <tr><td colspan="4" class="text-center text-body-secondary py-4">No categories yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><?= e($category['name']) ?></td>
                                <td><?= (int) $category['ticket_count'] ?></td>
                                <td><?= (int) $category['group_count'] ?></td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                data-toggle="modal" data-target="#editCategoryModal<?= (int) $category['id'] ?>">
                                            Edit
                                        </button>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Remove this category? This cannot be undone.');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_category">
                                            <input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                    <?= count($categories) <= 1 ? 'disabled' : '' ?>>
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
    </div>
</div>

<?php foreach ($categories as $category): ?>
    <div class="modal fade" id="editCategoryModal<?= (int) $category['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_category">
                    <input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Category</h5>
                        <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-0">
                            <label class="form-label" for="edit_category_name_<?= (int) $category['id'] ?>">Category Name</label>
                            <input class="form-control" id="edit_category_name_<?= (int) $category['id'] ?>" name="name" required maxlength="50" value="<?= e($category['name']) ?>">
                            <div class="form-text">Renaming updates this category on every existing ticket.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>
