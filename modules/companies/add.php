<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

require_role('super_admin');

if (is_post()) {
    verify_csrf();
    $stmt = db()->prepare('INSERT INTO companies (name, email, contact_name, phone, address_line1, city, state, postal_code, country, status, created_at, updated_at) VALUES (:name, :email, :contact_name, :phone, :address_line1, :city, :state, :postal_code, :country, :status, NOW(), NOW())');
    $stmt->execute([
        'name' => request_string('name'),
        'email' => request_string('email'),
        'contact_name' => request_string('contact_name'),
        'phone' => request_string('phone'),
        'address_line1' => request_string('address_line1'),
        'city' => request_string('city'),
        'state' => request_string('state'),
        'postal_code' => request_string('postal_code'),
        'country' => request_string('country'),
        'status' => request_string('status', 'active'),
    ]);
    set_flash('success', 'Company created successfully.');
    redirect('/modules/companies/index.php');
}

$pageTitle = 'Add Company';
require BASE_PATH . '/includes/header.php';
?>
<div class="card border-0 shadow-sm"><div class="card-body">
<form method="post" class="row g-3"><?= csrf_field() ?>
    <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" required></div>
    <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email"></div>
    <div class="col-md-6"><label class="form-label">Contact Name</label><input class="form-control" name="contact_name"></div>
    <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone"></div>
    <div class="col-md-6"><label class="form-label">Address</label><input class="form-control" name="address_line1"></div>
    <div class="col-md-3"><label class="form-label">City</label><input class="form-control" name="city"></div>
    <div class="col-md-3"><label class="form-label">State</label><input class="form-control" name="state"></div>
    <div class="col-md-3"><label class="form-label">Postal Code</label><input class="form-control" name="postal_code"></div>
    <div class="col-md-3"><label class="form-label">Country</label><input class="form-control" name="country"></div>
    <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
    <div class="col-12"><button class="btn btn-primary">Save</button> <a class="btn btn-link" href="/modules/companies/index.php">Cancel</a></div>
</form>
</div></div>
<?php require BASE_PATH . '/includes/footer.php'; ?>
