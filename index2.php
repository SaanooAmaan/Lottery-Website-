<?php
session_start();

// ─── DATABASE SETUP (SQLite via PDO) ────────────────────────────────────────
$db_file = __DIR__ . '/database.sqlite';
$pdo = new PDO("sqlite:$db_file");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create tables
$pdo->exec("
CREATE TABLE IF NOT EXISTS admins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    avatar TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    phone TEXT NOT NULL,
    country_code TEXT NOT NULL,
    country TEXT NOT NULL,
    address TEXT,
    payment_screenshot TEXT DEFAULT NULL,
    payment_status TEXT DEFAULT 'pending',
    avatar TEXT DEFAULT NULL,
    registered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME DEFAULT NULL
);
");

// Add lottery columns if not exist (migration-safe)
try { $pdo->exec("ALTER TABLE users ADD COLUMN lottery_number TEXT DEFAULT NULL"); } catch(Exception $e){}
try { $pdo->exec("ALTER TABLE users ADD COLUMN lottery_assigned_at DATETIME DEFAULT NULL"); } catch(Exception $e){}

// Seed default admin
$check = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
if ($check == 0) {
    $hash = password_hash('Admin@1234', PASSWORD_DEFAULT);
    $pdo->exec("INSERT INTO admins (name, email, password) VALUES ('Super Admin', 'admin@system.com', '$hash')");
}

// ─── HELPER FUNCTIONS ────────────────────────────────────────────────────────
function flash($msg, $type = 'success') {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}
function get_flash() {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}
function redirect($url) {
    header("Location: $url");
    exit;
}
function is_admin() { return isset($_SESSION['admin_id']); }
function is_user()  { return isset($_SESSION['user_id']); }
function sanitize($v) { return htmlspecialchars(trim($v), ENT_QUOTES); }
function upload_file($file, $prefix = '') {
    $uploads = __DIR__ . '/uploads/';
    if (!is_dir($uploads)) mkdir($uploads, 0755, true);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp','pdf'];
    if (!in_array($ext, $allowed)) return false;
    if ($file['size'] > 5 * 1024 * 1024) return false;
    $fname = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $uploads . $fname)) return $fname;
    return false;
}
function thumb($filename) {
    if (!$filename) return null;
    return 'uploads/' . $filename;
}

// Country codes data
$country_codes = [
    ["code"=>"+251","country"=>"Ethiopia","iso"=>"ET","flag"=>"🇪🇹","pattern"=>"## ### ####"],
    ["code"=>"+1","country"=>"United States","iso"=>"US","flag"=>"🇺🇸","pattern"=>"(###) ###-####"],
    ["code"=>"+1","country"=>"Canada","iso"=>"CA","flag"=>"🇨🇦","pattern"=>"(###) ###-####"],
    ["code"=>"+44","country"=>"United Kingdom","iso"=>"GB","flag"=>"🇬🇧","pattern"=>"#### ######"],
    ["code"=>"+49","country"=>"Germany","iso"=>"DE","flag"=>"🇩🇪","pattern"=>"### #######"],
    ["code"=>"+33","country"=>"France","iso"=>"FR","flag"=>"🇫🇷","pattern"=>"## ## ## ## ##"],
    ["code"=>"+39","country"=>"Italy","iso"=>"IT","flag"=>"🇮🇹","pattern"=>"### #######"],
    ["code"=>"+34","country"=>"Spain","iso"=>"ES","flag"=>"🇪🇸","pattern"=>"### ### ###"],
    ["code"=>"+7","country"=>"Russia","iso"=>"RU","flag"=>"🇷🇺","pattern"=>"(###) ###-##-##"],
    ["code"=>"+86","country"=>"China","iso"=>"CN","flag"=>"🇨🇳","pattern"=>"### #### ####"],
    ["code"=>"+81","country"=>"Japan","iso"=>"JP","flag"=>"🇯🇵","pattern"=>"##-####-####"],
    ["code"=>"+82","country"=>"South Korea","iso"=>"KR","flag"=>"🇰🇷","pattern"=>"##-####-####"],
    ["code"=>"+91","country"=>"India","iso"=>"IN","flag"=>"🇮🇳","pattern"=>"##### #####"],
    ["code"=>"+92","country"=>"Pakistan","iso"=>"PK","flag"=>"🇵🇰","pattern"=>"### #######"],
    ["code"=>"+880","country"=>"Bangladesh","iso"=>"BD","flag"=>"🇧🇩","pattern"=>"####-######"],
    ["code"=>"+94","country"=>"Sri Lanka","iso"=>"LK","flag"=>"🇱🇰","pattern"=>"## ### ####"],
    ["code"=>"+254","country"=>"Kenya","iso"=>"KE","flag"=>"🇰🇪","pattern"=>"### ######"],
    ["code"=>"+234","country"=>"Nigeria","iso"=>"NG","flag"=>"🇳🇬","pattern"=>"### ### ####"],
    ["code"=>"+27","country"=>"South Africa","iso"=>"ZA","flag"=>"🇿🇦","pattern"=>"## ### ####"],
    ["code"=>"+20","country"=>"Egypt","iso"=>"EG","flag"=>"🇪🇬","pattern"=>"## #### ####"],
    ["code"=>"+212","country"=>"Morocco","iso"=>"MA","flag"=>"🇲🇦","pattern"=>"##-######"],
    ["code"=>"+213","country"=>"Algeria","iso"=>"DZ","flag"=>"🇩🇿","pattern"=>"### ## ## ##"],
    ["code"=>"+216","country"=>"Tunisia","iso"=>"TN","flag"=>"🇹🇳","pattern"=>"## ### ###"],
    ["code"=>"+55","country"=>"Brazil","iso"=>"BR","flag"=>"🇧🇷","pattern"=>"(##) #####-####"],
    ["code"=>"+52","country"=>"Mexico","iso"=>"MX","flag"=>"🇲🇽","pattern"=>"### ### ####"],
    ["code"=>"+54","country"=>"Argentina","iso"=>"AR","flag"=>"🇦🇷","pattern"=>"## ####-####"],
    ["code"=>"+61","country"=>"Australia","iso"=>"AU","flag"=>"🇦🇺","pattern"=>"#### ### ###"],
    ["code"=>"+64","country"=>"New Zealand","iso"=>"NZ","flag"=>"🇳🇿","pattern"=>"## ### ####"],
    ["code"=>"+966","country"=>"Saudi Arabia","iso"=>"SA","flag"=>"🇸🇦","pattern"=>"## ### ####"],
    ["code"=>"+971","country"=>"UAE","iso"=>"AE","flag"=>"🇦🇪","pattern"=>"## ### ####"],
    ["code"=>"+90","country"=>"Turkey","iso"=>"TR","flag"=>"🇹🇷","pattern"=>"(###) ### ####"],
    ["code"=>"+98","country"=>"Iran","iso"=>"IR","flag"=>"🇮🇷","pattern"=>"### ### ####"],
    ["code"=>"+62","country"=>"Indonesia","iso"=>"ID","flag"=>"🇮🇩","pattern"=>"####-####-####"],
    ["code"=>"+63","country"=>"Philippines","iso"=>"PH","flag"=>"🇵🇭","pattern"=>"### ### ####"],
    ["code"=>"+60","country"=>"Malaysia","iso"=>"MY","flag"=>"🇲🇾","pattern"=>"##-#### ####"],
    ["code"=>"+66","country"=>"Thailand","iso"=>"TH","flag"=>"🇹🇭","pattern"=>"##-###-####"],
    ["code"=>"+84","country"=>"Vietnam","iso"=>"VN","flag"=>"🇻🇳","pattern"=>"### ### ####"],
    ["code"=>"+65","country"=>"Singapore","iso"=>"SG","flag"=>"🇸🇬","pattern"=>"#### ####"],
    ["code"=>"+353","country"=>"Ireland","iso"=>"IE","flag"=>"🇮🇪","pattern"=>"## #######"],
    ["code"=>"+31","country"=>"Netherlands","iso"=>"NL","flag"=>"🇳🇱","pattern"=>"## ### ####"],
    ["code"=>"+32","country"=>"Belgium","iso"=>"BE","flag"=>"🇧🇪","pattern"=>"### ## ## ##"],
    ["code"=>"+41","country"=>"Switzerland","iso"=>"CH","flag"=>"🇨🇭","pattern"=>"## ### ## ##"],
    ["code"=>"+43","country"=>"Austria","iso"=>"AT","flag"=>"🇦🇹","pattern"=>"### #######"],
    ["code"=>"+46","country"=>"Sweden","iso"=>"SE","flag"=>"🇸🇪","pattern"=>"##-### ## ##"],
    ["code"=>"+47","country"=>"Norway","iso"=>"NO","flag"=>"🇳🇴","pattern"=>"### ## ###"],
    ["code"=>"+45","country"=>"Denmark","iso"=>"DK","flag"=>"🇩🇰","pattern"=>"## ## ## ##"],
    ["code"=>"+358","country"=>"Finland","iso"=>"FI","flag"=>"🇫🇮","pattern"=>"## ### ####"],
    ["code"=>"+48","country"=>"Poland","iso"=>"PL","flag"=>"🇵🇱","pattern"=>"### ### ###"],
    ["code"=>"+380","country"=>"Ukraine","iso"=>"UA","flag"=>"🇺🇦","pattern"=>"## ### ## ##"],
    ["code"=>"+36","country"=>"Hungary","iso"=>"HU","flag"=>"🇭🇺","pattern"=>"## ### ####"],
    ["code"=>"+420","country"=>"Czech Republic","iso"=>"CZ","flag"=>"🇨🇿","pattern"=>"### ### ###"],
    ["code"=>"+40","country"=>"Romania","iso"=>"RO","flag"=>"🇷🇴","pattern"=>"### ### ###"],
];

// ─── ACTIONS ────────────────────────────────────────────────────────────────
$action = $_GET['action'] ?? 'home';

// Admin Login
if ($action === 'admin_login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $pass  = $_POST['password'];
    $admin = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
    $admin->execute([$email]);
    $admin = $admin->fetch(PDO::FETCH_ASSOC);
    if ($admin && password_verify($pass, $admin['password'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        flash('Welcome back, ' . $admin['name'] . '!');
        redirect('?action=admin_dashboard');
    } else {
        flash('Invalid credentials.', 'error');
        redirect('?action=admin_login');
    }
}

// Admin Logout
if ($action === 'admin_logout') {
    unset($_SESSION['admin_id'], $_SESSION['admin_name']);
    flash('You have been logged out.');
    redirect('?action=admin_login');
}

// Admin Update Profile
if ($action === 'admin_update_profile' && is_admin() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $admin = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
    $admin->execute([$_SESSION['admin_id']]);
    $admin = $admin->fetch(PDO::FETCH_ASSOC);

    $avatar = $admin['avatar'];
    if (!empty($_FILES['avatar']['name'])) {
        $uploaded = upload_file($_FILES['avatar'], 'admin');
        if ($uploaded) $avatar = $uploaded;
    }

    $fields = "name=?, email=?, avatar=?";
    $params = [$name, $email, $avatar];
    if (!empty($_POST['password'])) {
        $fields .= ", password=?";
        $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
    }
    $params[] = $_SESSION['admin_id'];
    $pdo->prepare("UPDATE admins SET $fields WHERE id=?")->execute($params);
    $_SESSION['admin_name'] = $name;
    flash('Profile updated successfully!');
    redirect('?action=admin_profile');
}

// Admin Accept/Reject Payment
if ($action === 'update_payment' && is_admin() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid    = (int)$_POST['user_id'];
    $status = in_array($_POST['status'], ['approved','rejected']) ? $_POST['status'] : 'pending';
    $pdo->prepare("UPDATE users SET payment_status=? WHERE id=?")->execute([$status, $uid]);
    flash('Payment status updated to ' . strtoupper($status) . '.', $status === 'approved' ? 'success' : 'error');
    redirect('?action=admin_users');
}

// Admin Assign Lottery Number
if ($action === 'assign_lottery' && is_admin() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = (int)$_POST['user_id'];
    // Check user is approved
    $chk = $pdo->prepare("SELECT payment_status, lottery_number FROM users WHERE id=?");
    $chk->execute([$uid]);
    $chk = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$chk || $chk['payment_status'] !== 'approved') {
        flash('User must be approved before assigning a lottery number.', 'error');
        redirect('?action=admin_users');
    }
    // Generate unique 6-digit number
    $attempts = 0;
    do {
        $num = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $exists = $pdo->prepare("SELECT id FROM users WHERE lottery_number=? AND id!=?");
        $exists->execute([$num, $uid]);
        $attempts++;
    } while ($exists->fetch() && $attempts < 100);
    $pdo->prepare("UPDATE users SET lottery_number=?, lottery_assigned_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$num, $uid]);
    flash("Lottery number $num assigned successfully!", 'success');
    redirect('?action=admin_users');
}

// Admin Revoke Lottery Number
if ($action === 'revoke_lottery' && is_admin() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = (int)$_POST['user_id'];
    $pdo->prepare("UPDATE users SET lottery_number=NULL, lottery_assigned_at=NULL WHERE id=?")->execute([$uid]);
    flash('Lottery number revoked.', 'error');
    redirect('?action=admin_users');
}

// User Register
if ($action === 'user_register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = sanitize($_POST['full_name']);
    $email   = sanitize($_POST['email']);
    $pass    = $_POST['password'];
    $phone   = sanitize($_POST['phone']);
    $cc      = sanitize($_POST['country_code']);
    $country = sanitize($_POST['country']);
    $address = sanitize($_POST['address'] ?? '');

    // Check existing
    $exist = $pdo->prepare("SELECT id FROM users WHERE email=?");
    $exist->execute([$email]);
    if ($exist->fetch()) {
        flash('Email already registered.', 'error');
        redirect('?action=user_register');
    }

    $avatar = null;
    if (!empty($_FILES['avatar']['name'])) {
        $uploaded = upload_file($_FILES['avatar'], 'user');
        if ($uploaded) $avatar = $uploaded;
    }

    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, phone, country_code, country, address, avatar) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([$name, $email, $hash, $phone, $cc, $country, $address, $avatar]);
    flash('Registration successful! Please log in.');
    redirect('?action=user_login');
}

// User Login
if ($action === 'user_login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $pass  = $_POST['password'];
    $user  = $pdo->prepare("SELECT * FROM users WHERE email=?");
    $user->execute([$email]);
    $user = $user->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($pass, $user['password'])) {
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $pdo->prepare("UPDATE users SET last_login=CURRENT_TIMESTAMP WHERE id=?")->execute([$user['id']]);
        flash('Welcome, ' . $user['full_name'] . '!');
        redirect('?action=user_dashboard');
    } else {
        flash('Invalid credentials.', 'error');
        redirect('?action=user_login');
    }
}

// User Logout
if ($action === 'user_logout') {
    unset($_SESSION['user_id'], $_SESSION['user_name']);
    flash('Logged out successfully.');
    redirect('?action=user_login');
}

// User Upload Payment
if ($action === 'upload_payment' && is_user() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_FILES['payment']['name'])) {
        $uploaded = upload_file($_FILES['payment'], 'payment');
        if ($uploaded) {
            $pdo->prepare("UPDATE users SET payment_screenshot=?, payment_status='pending' WHERE id=?")->execute([$uploaded, $_SESSION['user_id']]);
            flash('Payment screenshot uploaded! Awaiting admin review.');
        } else {
            flash('Invalid file. Use JPG, PNG, or PDF under 5MB.', 'error');
        }
    }
    redirect('?action=user_dashboard');
}

// ─── DATA FETCHING ───────────────────────────────────────────────────────────
$current_admin = null;
$current_user  = null;
$users_list    = [];
$flash         = get_flash();

if (is_admin()) {
    $a = $pdo->prepare("SELECT * FROM admins WHERE id=?");
    $a->execute([$_SESSION['admin_id']]);
    $current_admin = $a->fetch(PDO::FETCH_ASSOC);
}
if (is_user()) {
    $u = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $u->execute([$_SESSION['user_id']]);
    $current_user = $u->fetch(PDO::FETCH_ASSOC);
}
if (is_admin() && in_array($action, ['admin_dashboard', 'admin_users'])) {
    $users_list = $pdo->query("SELECT * FROM users ORDER BY registered_at DESC")->fetchAll(PDO::FETCH_ASSOC);
}

$stats = [];
if (is_admin()) {
    $stats['total']    = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['pending']  = $pdo->query("SELECT COUNT(*) FROM users WHERE payment_status='pending'")->fetchColumn();
    $stats['approved'] = $pdo->query("SELECT COUNT(*) FROM users WHERE payment_status='approved'")->fetchColumn();
    $stats['rejected'] = $pdo->query("SELECT COUNT(*) FROM users WHERE payment_status='rejected'")->fetchColumn();
    $stats['lottery']  = $pdo->query("SELECT COUNT(*) FROM users WHERE lottery_number IS NOT NULL")->fetchColumn();
}

// Route guards
if (in_array($action, ['admin_dashboard','admin_users','admin_profile']) && !is_admin()) {
    redirect('?action=admin_login');
}
if (in_array($action, ['user_dashboard']) && !is_user()) {
    redirect('?action=user_login');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AdminHub Pro — Management System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {
  --bg:        #0a0d14;
  --surface:   #111520;
  --surface2:  #161c2e;
  --surface3:  #1e2640;
  --border:    #252d45;
  --border2:   #2e3858;
  --accent:    #4f8cff;
  --accent2:   #7c5cfc;
  --gold:      #f5c842;
  --success:   #30d98a;
  --danger:    #ff4d6d;
  --warn:      #ffb347;
  --text:      #e8eaf0;
  --muted:     #7a84a0;
  --font-head: 'Syne', sans-serif;
  --font-body: 'DM Sans', sans-serif;
  --r:         12px;
  --r2:        20px;
  --shadow:    0 4px 32px rgba(0,0,0,.5);
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body {
  font-family: var(--font-body);
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
  font-size: 15px;
  line-height: 1.6;
}
a { color: var(--accent); text-decoration: none; }
a:hover { text-decoration: underline; }
img { max-width: 100%; }

/* ── UTILITY ── */
.flex   { display: flex; }
.grid   { display: grid; }
.gap-1  { gap: .5rem; }
.gap-2  { gap: 1rem; }
.gap-3  { gap: 1.5rem; }
.center { align-items: center; justify-content: center; }
.between{ align-items: center; justify-content: space-between; }
.wrap   { flex-wrap: wrap; }
.col    { flex-direction: column; }
.w100   { width: 100%; }
.mt-1   { margin-top:.5rem; }
.mt-2   { margin-top:1rem; }
.mt-3   { margin-top:1.5rem; }
.mb-2   { margin-bottom:1rem; }
.mb-3   { margin-bottom:1.5rem; }
.small  { font-size:13px; color:var(--muted); }
.bold   { font-weight:600; }
.mono   { font-family: 'Courier New', monospace; }

/* ── SCROLLBAR ── */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: var(--surface); }
::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 3px; }

/* ── BADGE ── */
.badge {
  display: inline-block;
  padding: .2rem .7rem;
  border-radius: 50px;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: .03em;
  text-transform: uppercase;
}
.badge-pending  { background: rgba(255,179,71,.15); color: var(--warn); }
.badge-approved { background: rgba(48,217,138,.15); color: var(--success); }
.badge-rejected { background: rgba(255,77,109,.15); color: var(--danger); }
.badge-info     { background: rgba(79,140,255,.15); color: var(--accent); }

/* ── BUTTON ── */
.btn {
  display: inline-flex; align-items: center; gap: .5rem;
  padding: .6rem 1.4rem;
  border-radius: var(--r);
  font-family: var(--font-body);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: .2s;
  white-space: nowrap;
}
.btn-primary   { background: var(--accent); color: #fff; }
.btn-primary:hover { background: #6ba0ff; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(79,140,255,.35); }
.btn-purple    { background: var(--accent2); color: #fff; }
.btn-purple:hover { background: #9474ff; }
.btn-success   { background: var(--success); color: #0a0d14; }
.btn-success:hover { background: #4fffa8; }
.btn-danger    { background: var(--danger); color: #fff; }
.btn-danger:hover { background: #ff7090; }
.btn-ghost     { background: transparent; border: 1.5px solid var(--border2); color: var(--text); }
.btn-ghost:hover { border-color: var(--accent); color: var(--accent); }
.btn-sm { padding: .35rem .9rem; font-size: 13px; }
.btn-lg { padding: .9rem 2rem; font-size: 16px; border-radius: 14px; }
.btn-icon { padding: .5rem; border-radius: 8px; }

/* ── CARD ── */
.card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--r2);
  padding: 1.5rem;
}
.card-header {
  font-family: var(--font-head);
  font-size: 16px;
  font-weight: 700;
  margin-bottom: 1.2rem;
  display: flex;
  align-items: center;
  gap: .6rem;
}

/* ── FLASH ── */
.flash {
  padding: .9rem 1.4rem;
  border-radius: var(--r);
  font-weight: 500;
  margin-bottom: 1.5rem;
  display: flex;
  align-items: center;
  gap: .7rem;
  animation: slideIn .3s ease;
}
.flash-success { background: rgba(48,217,138,.12); border: 1px solid rgba(48,217,138,.3); color: var(--success); }
.flash-error   { background: rgba(255,77,109,.12); border: 1px solid rgba(255,77,109,.3); color: var(--danger); }
@keyframes slideIn { from { opacity:0; transform: translateY(-8px); } to { opacity:1; transform: translateY(0); } }

/* ── FORM ── */
.form-group   { margin-bottom: 1.2rem; }
.form-label   { display: block; font-size: 13px; font-weight: 600; color: var(--muted); margin-bottom: .4rem; letter-spacing: .04em; text-transform: uppercase; }
.form-control {
  width: 100%;
  padding: .7rem 1rem;
  background: var(--surface2);
  border: 1.5px solid var(--border);
  border-radius: var(--r);
  color: var(--text);
  font-family: var(--font-body);
  font-size: 14px;
  transition: .2s;
  outline: none;
}
.form-control:focus { border-color: var(--accent); background: var(--surface3); box-shadow: 0 0 0 3px rgba(79,140,255,.1); }
.form-control::placeholder { color: var(--muted); }
select.form-control option { background: var(--surface2); }
.phone-row { display: flex; gap: .5rem; }
.phone-row .cc-select { width: 180px; flex-shrink: 0; }
.input-hint { font-size: 12px; color: var(--muted); margin-top: .3rem; }

/* ── TABLE ── */
.table-wrap  { overflow-x: auto; border-radius: var(--r); }
table        { width: 100%; border-collapse: collapse; font-size: 14px; }
th           { background: var(--surface2); color: var(--muted); font-size: 11px; letter-spacing: .08em; text-transform: uppercase; padding: .75rem 1rem; text-align: left; border-bottom: 1px solid var(--border); font-weight: 600; }
td           { padding: .85rem 1rem; border-bottom: 1px solid var(--border); vertical-align: middle; }
tr:last-child td { border-bottom: none; }
tr:hover td  { background: rgba(255,255,255,.02); }

/* ── AVATAR ── */
.avatar      { border-radius: 50%; object-fit: cover; }
.avatar-sm   { width: 36px; height: 36px; }
.avatar-md   { width: 56px; height: 56px; }
.avatar-lg   { width: 80px; height: 80px; }
.avatar-placeholder {
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-weight: 700;
  font-family: var(--font-head);
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  color: #fff;
  flex-shrink: 0;
}
.avatar-placeholder.sm  { width: 36px; height: 36px; font-size: 14px; }
.avatar-placeholder.md  { width: 56px; height: 56px; font-size: 20px; }
.avatar-placeholder.lg  { width: 80px; height: 80px; font-size: 28px; }

/* ══════════════════════════════════════════════
   AUTH PAGES
══════════════════════════════════════════════ */
.auth-wrap {
  min-height: 100vh;
  display: grid;
  grid-template-columns: 1fr 1fr;
}
@media (max-width: 768px) { .auth-wrap { grid-template-columns: 1fr; } }
.auth-left {
  background: linear-gradient(135deg, #0d1526 0%, #111d3a 50%, #0f1e3d 100%);
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: flex-start;
  padding: 4rem 3rem;
  position: relative;
  overflow: hidden;
}
.auth-left::before {
  content: '';
  position: absolute;
  width: 400px; height: 400px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(79,140,255,.15) 0%, transparent 70%);
  top: -100px; right: -100px;
}
.auth-left::after {
  content: '';
  position: absolute;
  width: 300px; height: 300px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(124,92,252,.1) 0%, transparent 70%);
  bottom: -50px; left: 50px;
}
.auth-logo {
  font-family: var(--font-head);
  font-size: 28px;
  font-weight: 800;
  display: flex;
  align-items: center;
  gap: .7rem;
  margin-bottom: 3rem;
  z-index: 1;
}
.auth-logo span { color: var(--accent); }
.auth-tagline {
  font-family: var(--font-head);
  font-size: 38px;
  font-weight: 800;
  line-height: 1.2;
  margin-bottom: 1.5rem;
  z-index: 1;
}
.auth-tagline em { font-style: normal; color: var(--accent); }
.auth-sub {
  color: var(--muted);
  font-size: 15px;
  max-width: 380px;
  line-height: 1.7;
  z-index: 1;
}
.auth-right {
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 3rem 4rem;
  background: var(--bg);
}
@media (max-width: 900px) { .auth-right { padding: 2rem; } }
.auth-title {
  font-family: var(--font-head);
  font-size: 26px;
  font-weight: 800;
  margin-bottom: .4rem;
}
.auth-sub2 {
  color: var(--muted);
  font-size: 14px;
  margin-bottom: 2rem;
}
.auth-switch {
  margin-top: 1.5rem;
  text-align: center;
  font-size: 14px;
  color: var(--muted);
}
.divider {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin: 1.5rem 0;
  color: var(--muted);
  font-size: 13px;
}
.divider::before, .divider::after {
  content: ''; flex: 1;
  height: 1px;
  background: var(--border);
}

/* ══════════════════════════════════════════════
   ADMIN LAYOUT
══════════════════════════════════════════════ */
.layout { display: flex; min-height: 100vh; }
.sidebar {
  width: 260px;
  background: var(--surface);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0; left: 0;
  height: 100vh;
  z-index: 100;
  transition: .3s;
}
.sidebar-logo {
  padding: 1.5rem 1.5rem 1rem;
  font-family: var(--font-head);
  font-size: 20px;
  font-weight: 800;
  display: flex;
  align-items: center;
  gap: .6rem;
  border-bottom: 1px solid var(--border);
}
.sidebar-logo .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--accent); }
.sidebar-nav { flex: 1; padding: 1rem 0; overflow-y: auto; }
.nav-section { padding: .5rem 1.5rem .3rem; font-size: 11px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--muted); }
.nav-item {
  display: flex;
  align-items: center;
  gap: .75rem;
  padding: .7rem 1.5rem;
  color: var(--muted);
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: .15s;
  border-left: 3px solid transparent;
  text-decoration: none;
}
.nav-item:hover  { color: var(--text); background: rgba(255,255,255,.04); text-decoration: none; }
.nav-item.active { color: var(--accent); background: rgba(79,140,255,.08); border-left-color: var(--accent); font-weight: 600; }
.nav-item svg { flex-shrink: 0; }
.sidebar-foot {
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--border);
}
.sidebar-user {
  display: flex;
  align-items: center;
  gap: .75rem;
  padding: .75rem;
  border-radius: var(--r);
  background: var(--surface2);
}
.sidebar-user-info { flex: 1; min-width: 0; }
.sidebar-user-name { font-size: 14px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sidebar-user-role { font-size: 12px; color: var(--muted); }

.main-content {
  margin-left: 260px;
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}
.topbar {
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  padding: 1rem 2rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: sticky;
  top: 0;
  z-index: 50;
}
.topbar-title { font-family: var(--font-head); font-size: 18px; font-weight: 700; }
.page-content { padding: 2rem; flex: 1; }

/* ── STAT CARDS ── */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
.stat-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--r2);
  padding: 1.4rem;
  display: flex;
  flex-direction: column;
  gap: .7rem;
  position: relative;
  overflow: hidden;
}
.stat-card::after {
  content: '';
  position: absolute;
  width: 80px; height: 80px;
  border-radius: 50%;
  top: -20px; right: -20px;
  opacity: .1;
}
.stat-blue::after   { background: var(--accent); }
.stat-green::after  { background: var(--success); }
.stat-red::after    { background: var(--danger); }
.stat-yellow::after { background: var(--warn); }
.stat-label { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: var(--muted); }
.stat-value { font-family: var(--font-head); font-size: 36px; font-weight: 800; line-height: 1; }
.stat-icon  { font-size: 22px; }
.stat-blue   .stat-value { color: var(--accent); }
.stat-green  .stat-value { color: var(--success); }
.stat-red    .stat-value { color: var(--danger); }
.stat-yellow .stat-value { color: var(--warn); }

/* ── USER PROFILE IN DASHBOARD ── */
.profile-grid { display: grid; grid-template-columns: 320px 1fr; gap: 1.5rem; }
@media (max-width: 960px) { .profile-grid { grid-template-columns: 1fr; } }
.profile-card { text-align: center; padding: 2rem; }
.profile-name { font-family: var(--font-head); font-size: 22px; font-weight: 800; margin: 1rem 0 .3rem; }
.profile-email { color: var(--muted); font-size: 14px; }
.profile-meta { margin-top: 1.2rem; display: flex; flex-direction: column; gap: .5rem; }
.profile-meta-row { display: flex; justify-content: space-between; font-size: 13px; padding: .5rem 0; border-bottom: 1px solid var(--border); }
.profile-meta-row:last-child { border-bottom: none; }

/* ── WELCOME VILLA BANNER ── */
.villa-banner {
  position: relative;
  overflow: hidden;
  border-radius: var(--r2);
  padding: 1.6rem 2rem;
  margin-bottom: 1.5rem;
  background: linear-gradient(120deg, #0d1f12 0%, #112916 40%, #1a3a20 100%);
  border: 1.5px solid rgba(80,200,100,.22);
  display: flex;
  align-items: center;
  gap: 1.4rem;
  flex-wrap: wrap;
}
.villa-banner::before {
  content: '';
  position: absolute;
  inset: 0;
  background: repeating-linear-gradient(-45deg,transparent,transparent 18px,rgba(80,200,100,.03) 18px,rgba(80,200,100,.03) 36px);
  pointer-events: none;
}
.villa-banner::after {
  content: '';
  position: absolute;
  top: 0; left: -100%;
  width: 60%; height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,.05), transparent);
  animation: shimmerSweep 3.5s ease-in-out infinite;
  pointer-events: none;
}
@keyframes shimmerSweep {
  0%  { left: -80%; }
  60% { left: 130%; }
  100%{ left: 130%; }
}
.villa-icon {
  font-size: 44px;
  animation: houseBounce 2.2s ease-in-out infinite;
  flex-shrink: 0;
  filter: drop-shadow(0 4px 14px rgba(80,200,100,.45));
}
@keyframes houseBounce {
  0%,100%{ transform:translateY(0) scale(1); }
  45%    { transform:translateY(-6px) scale(1.07); }
  65%    { transform:translateY(-2px) scale(1.03); }
}
.villa-text-wrap { flex: 1; min-width: 0; }
.villa-intro { font-size: 14px; color: rgba(200,230,210,.8); margin-bottom: .35rem; line-height: 1.5; }
.villa-headline {
  font-family: var(--font-head);
  font-size: 22px;
  font-weight: 800;
  line-height: 1.3;
  color: #fff;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: .4rem;
}
.villa-highlight {
  display: inline-block;
  position: relative;
  padding: .1rem .6rem .15rem;
  white-space: nowrap;
  color: #0a1f0d;
  font-weight: 900;
  letter-spacing: -.01em;
  z-index: 0;
}
.villa-highlight::before {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: 6px;
  background: linear-gradient(90deg,#4dda7a,#80f0a0,#4dda7a);
  background-size: 200% 100%;
  animation: villaGlow 2s linear infinite;
  z-index: -1;
}
@keyframes villaGlow {
  0%  { background-position:0% 50%; box-shadow:0 0 0 rgba(77,218,122,0); }
  50% { background-position:100% 50%; box-shadow:0 0 18px rgba(77,218,122,.55); }
  100%{ background-position:0% 50%; box-shadow:0 0 0 rgba(77,218,122,0); }
}
.villa-highlight::after {
  content: '';
  position: absolute;
  inset: -3px;
  border-radius: 9px;
  border: 2px solid #4dda7a;
  opacity: 0;
  animation: ringPulse 2s ease-in-out infinite;
}
@keyframes ringPulse {
  0%  { opacity:0; transform:scale(.95); }
  40% { opacity:.7; transform:scale(1.02); }
  100%{ opacity:0; transform:scale(1.1); }
}
.villa-deposit { margin-top:.5rem; font-size:13px; color:rgba(180,220,190,.7); display:flex; align-items:center; gap:.4rem; }
.villa-deposit strong { color:#4dda7a; }
.villa-badge {
  flex-shrink: 0;
  background: rgba(77,218,122,.12);
  border: 1.5px solid rgba(77,218,122,.35);
  border-radius: var(--r);
  padding: .6rem 1rem;
  text-align: center;
}
.villa-badge-amount {
  font-family: var(--font-head);
  font-size: 20px;
  font-weight: 800;
  color: #4dda7a;
  display: block;
  animation: countPop 1.5s ease-in-out infinite alternate;
}
@keyframes countPop {
  from { transform:scale(1); }
  to   { transform:scale(1.08); }
}
.villa-badge-label { font-size:11px; color:rgba(180,220,190,.6); font-weight:600; letter-spacing:.06em; text-transform:uppercase; }

/* ── LOTTERY BANNER ── */
.lottery-banner {
  position: relative;
  overflow: hidden;
  border-radius: var(--r2);
  padding: 1.5rem 2rem;
  margin-bottom: 1.2rem;
  background: linear-gradient(120deg,#0e0a1f 0%,#140d2e 50%,#1a0f3d 100%);
  border: 1.5px solid rgba(160,100,255,.28);
  display: flex;
  align-items: flex-start;
  gap: 1.4rem;
  flex-wrap: wrap;
}
/* Starfield dots */
.lottery-banner::before {
  content:'';
  position:absolute; inset:0;
  background-image:
    radial-gradient(circle,rgba(255,255,255,.55) 1px,transparent 1px),
    radial-gradient(circle,rgba(255,255,255,.3) 1px,transparent 1px),
    radial-gradient(circle,rgba(255,200,100,.4) 1px,transparent 1px);
  background-size:60px 60px,90px 90px,120px 120px;
  background-position:0 0,30px 30px,60px 10px;
  animation:starDrift 18s linear infinite;
  pointer-events:none; opacity:.4;
}
@keyframes starDrift {
  from{background-position:0 0,30px 30px,60px 10px;}
  to  {background-position:60px 60px,90px 90px,180px 70px;}
}
/* Shimmer */
.lottery-banner::after {
  content:'';
  position:absolute; top:0; left:-120%; width:60%; height:100%;
  background:linear-gradient(90deg,transparent,rgba(160,100,255,.12),transparent);
  animation:lottShimmer 4s ease-in-out infinite;
  pointer-events:none;
}
@keyframes lottShimmer {
  0%{left:-120%;} 60%{left:130%;} 100%{left:130%;}
}
.lottery-trophy {
  font-size:52px;
  animation:trophySpin 3s ease-in-out infinite;
  flex-shrink:0;
  filter:drop-shadow(0 0 16px rgba(255,200,60,.6));
  z-index:1;
}
@keyframes trophySpin {
  0%,100%{transform:rotate(-6deg) scale(1);}
  50%    {transform:rotate(6deg) scale(1.1);}
}
.lottery-text { flex:1; min-width:0; z-index:1; }
.lottery-intro {
  font-size:13px;
  color:rgba(200,180,255,.75);
  margin-bottom:.4rem;
  letter-spacing:.04em;
  text-transform:uppercase;
  font-weight:700;
}
.lottery-headline {
  font-family:var(--font-head);
  font-size:20px;
  font-weight:800;
  color:#fff;
  line-height:1.3;
  margin-bottom:.6rem;
}
/* "Lottery website" animated highlight */
.lott-hl {
  display:inline-block;
  position:relative;
  padding:.08rem .55rem .12rem;
  color:#0a0d14;
  font-weight:900;
  z-index:0;
  white-space:nowrap;
}
.lott-hl::before {
  content:'';
  position:absolute; inset:0;
  border-radius:5px;
  background:linear-gradient(90deg,#c084fc,#f472b6,#fb923c,#c084fc);
  background-size:300% 100%;
  animation:lottRainbow 2.5s linear infinite;
  z-index:-1;
}
@keyframes lottRainbow {
  0%  {background-position:0% 50%;}
  100%{background-position:300% 50%;}
}
.lott-hl::after {
  content:'';
  position:absolute; inset:-3px;
  border-radius:8px;
  border:2px solid #c084fc;
  opacity:0;
  animation:lottRing 2.5s ease-in-out infinite;
}
@keyframes lottRing {
  0%  {opacity:0;transform:scale(.94);}
  40% {opacity:.65;transform:scale(1.03);}
  100%{opacity:0;transform:scale(1.12);}
}
/* Rewards ticker */
.rewards-ticker {
  position:relative;
  overflow:hidden;
  background:rgba(160,100,255,.1);
  border:1px solid rgba(160,100,255,.2);
  border-radius:8px;
  padding:.35rem .7rem;
  margin-top:.5rem;
  display:flex;
  align-items:center;
  gap:.6rem;
}
.ticker-label {
  font-size:11px;
  font-weight:800;
  text-transform:uppercase;
  letter-spacing:.08em;
  color:#c084fc;
  white-space:nowrap;
  flex-shrink:0;
}
.ticker-track {
  display:flex;
  gap:1.6rem;
  animation:tickerScroll 12s linear infinite;
  white-space:nowrap;
}
.ticker-track:hover { animation-play-state:paused; }
@keyframes tickerScroll {
  0%  {transform:translateX(0);}
  100%{transform:translateX(-50%);}
}
.ticker-item {
  font-size:13px;
  font-weight:600;
  color:rgba(230,210,255,.85);
  display:flex;
  align-items:center;
  gap:.35rem;
}

/* ── LOTTERY NUMBER DISPLAY (user) ── */
.lottery-ticket {
  background:linear-gradient(135deg,#071a0f 0%,#0d2b18 100%);
  border:2px solid rgba(74,222,128,.35);
  border-radius:var(--r2);
  padding:1.8rem 2rem;
  text-align:center;
  position:relative;
  overflow:hidden;
  margin-bottom:1.5rem;
}
.lottery-ticket::before {
  content:'';
  position:absolute; inset:0;
  background:radial-gradient(ellipse at 50% 0%,rgba(74,222,128,.12) 0%,transparent 70%);
  pointer-events:none;
}
.lt-label {
  font-size:12px;
  font-weight:800;
  text-transform:uppercase;
  letter-spacing:.12em;
  color:rgba(74,222,128,.6);
  margin-bottom:.6rem;
}
.lt-number {
  font-family:'Courier New',monospace;
  font-size:52px;
  font-weight:900;
  color:#4ade80;
  letter-spacing:.2em;
  line-height:1;
  text-shadow:0 0 30px rgba(74,222,128,.6),0 0 60px rgba(74,222,128,.3);
  animation:numGlow 2s ease-in-out infinite alternate;
  display:block;
  margin-bottom:.6rem;
}
@keyframes numGlow {
  from{text-shadow:0 0 20px rgba(74,222,128,.5),0 0 40px rgba(74,222,128,.2);}
  to  {text-shadow:0 0 35px rgba(74,222,128,.9),0 0 70px rgba(74,222,128,.5),0 0 100px rgba(74,222,128,.2);}
}
/* Digit separators */
.lt-number-wrap { position:relative; display:inline-block; }
.lt-number-wrap::before,.lt-number-wrap::after {
  content:'✦';
  position:absolute; top:50%; transform:translateY(-50%);
  color:rgba(74,222,128,.4); font-size:14px;
  animation:starPop 1.8s ease-in-out infinite alternate;
}
.lt-number-wrap::before { left:-24px; animation-delay:0s; }
.lt-number-wrap::after  { right:-24px; animation-delay:.6s; }
@keyframes starPop {
  from{opacity:.3;transform:translateY(-50%) scale(.8);}
  to  {opacity:1;transform:translateY(-50%) scale(1.2);}
}
.lt-date { font-size:12px; color:rgba(74,222,128,.5); margin-top:.4rem; }
.lt-congrats {
  font-size:13px; color:rgba(74,222,128,.8); font-weight:600;
  margin-top:.7rem;
  animation:fadeInUp .5s ease both;
}
@keyframes fadeInUp {
  from{opacity:0;transform:translateY(8px);}
  to  {opacity:1;transform:translateY(0);}
}
/* No lottery yet */
.lt-none {
  background:var(--surface2);
  border:1.5px dashed var(--border2);
  border-radius:var(--r2);
  padding:1.8rem;
  text-align:center;
  margin-bottom:1.5rem;
  color:var(--muted);
}
.lt-none-icon { font-size:40px; margin-bottom:.6rem; }

/* ── LOTTERY COLUMN IN ADMIN TABLE ── */
.lnum {
  font-family:'Courier New',monospace;
  font-size:15px;
  font-weight:700;
  color:#4ade80;
  letter-spacing:.12em;
  text-shadow:0 0 8px rgba(74,222,128,.4);
}
.btn-lottery {
  background:linear-gradient(135deg,#7c3aed,#9333ea);
  color:#fff;
}
.btn-lottery:hover { background:linear-gradient(135deg,#9333ea,#a855f7); transform:translateY(-1px); }

/* CBE Account Banner */
.cbe-banner {
  background: linear-gradient(135deg, #14222f 0%, #1a2d42 100%);
  border: 1.5px solid rgba(245,200,66,.25);
  border-radius: var(--r2);
  padding: 1.5rem 2rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
  margin-bottom: 1.5rem;
}
.cbe-info { display: flex; flex-direction: column; gap: .3rem; }
.cbe-label { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: var(--gold); }
.cbe-account { font-family: 'Courier New', monospace; font-size: 24px; font-weight: 700; letter-spacing: .15em; color: #fff; }
.cbe-bank { font-size: 13px; color: var(--muted); }
.cbe-badge {
  background: rgba(245,200,66,.15);
  border: 1px solid rgba(245,200,66,.3);
  color: var(--gold);
  padding: .4rem 1rem;
  border-radius: 50px;
  font-size: 13px;
  font-weight: 700;
}

/* Payment Upload */
.upload-zone {
  border: 2px dashed var(--border2);
  border-radius: var(--r2);
  padding: 2.5rem;
  text-align: center;
  cursor: pointer;
  transition: .2s;
  background: var(--surface2);
}
.upload-zone:hover { border-color: var(--accent); background: rgba(79,140,255,.05); }
.upload-zone-icon { font-size: 40px; margin-bottom: .8rem; }
.upload-zone-text { color: var(--muted); font-size: 14px; }

/* Payment Preview */
.payment-thumb {
  width: 80px; height: 60px;
  object-fit: cover;
  border-radius: 8px;
  border: 1px solid var(--border);
  cursor: pointer;
}

/* Mobile toggle */
.hamburger { display: none; background: none; border: none; cursor: pointer; color: var(--text); }
@media (max-width: 900px) {
  .sidebar { left: -260px; }
  .sidebar.open { left: 0; box-shadow: 4px 0 32px rgba(0,0,0,.6); }
  .main-content { margin-left: 0; }
  .hamburger { display: flex; }
}

/* Register form 2-col */
.form-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 640px) { .form-2col { grid-template-columns: 1fr; } }

/* User page tabs */
.tabs { display: flex; gap: .5rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
.tab-btn {
  padding: .5rem 1.2rem;
  border-radius: var(--r);
  font-size: 14px;
  font-weight: 600;
  border: 1.5px solid var(--border);
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  transition: .15s;
}
.tab-btn.active { background: var(--accent); color: #fff; border-color: var(--accent); }
.tab-btn:hover:not(.active) { border-color: var(--accent); color: var(--accent); }
.tab-panel { display: none; }
.tab-panel.active { display: block; }

/* Modal */
.modal-bg {
  display: none;
  position: fixed; inset: 0;
  background: rgba(0,0,0,.7);
  z-index: 999;
  align-items: center;
  justify-content: center;
}
.modal-bg.open { display: flex; }
.modal {
  background: var(--surface);
  border: 1px solid var(--border2);
  border-radius: var(--r2);
  padding: 2rem;
  max-width: 520px;
  width: 94%;
  max-height: 90vh;
  overflow-y: auto;
  animation: popIn .25s ease;
}
@keyframes popIn { from { transform:scale(.92); opacity:0; } to { transform:scale(1); opacity:1; } }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
.modal-title { font-family: var(--font-head); font-size: 18px; font-weight: 800; }
.modal-close { background: none; border: none; font-size: 22px; color: var(--muted); cursor: pointer; transition: .15s; }
.modal-close:hover { color: var(--text); }

/* SVG icons */
.icon { display: inline-flex; align-items: center; justify-content: center; }

/* Section title */
.section-title { font-family: var(--font-head); font-size: 20px; font-weight: 800; margin-bottom: 1.5rem; }

/* Pagination / empty state */
.empty-state { text-align: center; padding: 3rem; color: var(--muted); }
.empty-state-icon { font-size: 48px; margin-bottom: 1rem; }

/* Switch tabs on home */
.role-switch {
  display: flex;
  background: var(--surface2);
  border-radius: var(--r);
  padding: .25rem;
  gap: .25rem;
  margin-bottom: 1.5rem;
}
.role-switch button {
  flex: 1;
  padding: .55rem;
  border-radius: 8px;
  border: none;
  background: transparent;
  color: var(--muted);
  font-family: var(--font-body);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: .2s;
}
.role-switch button.active { background: var(--accent); color: #fff; }

.tag {
  display: inline-block;
  padding: .15rem .6rem;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .04em;
  text-transform: uppercase;
}
.tag-admin { background: rgba(245,200,66,.15); color: var(--gold); }
.tag-user  { background: rgba(79,140,255,.15);  color: var(--accent); }

</style>
</head>
<body>

<?php // ══════════════ HOME (SWITCH) PAGE ══════════════
if ($action === 'home'): ?>
<div class="auth-wrap">
  <div class="auth-left">
    <div class="auth-logo">
      <svg width="32" height="32" viewBox="0 0 32 32"><rect width="32" height="32" rx="8" fill="#4f8cff" opacity=".15"/><path d="M8 12h16M8 16h10M8 20h13" stroke="#4f8cff" stroke-width="2" stroke-linecap="round"/><circle cx="24" cy="20" r="4" fill="#7c5cfc"/></svg>
      Admin<span>Hub</span> Pro
    </div>
    <div class="auth-tagline">Manage Users.<br><em>Own the System.</em></div>
    <p class="auth-sub">A professional management portal with admin controls, user registration, payment verification, and real-time status tracking.</p>
    <div class="flex gap-1 mt-3" style="flex-wrap:wrap;">
      <span class="badge badge-info">Secure Login</span>
      <span class="badge badge-approved">Payment Verification</span>
      <span class="badge badge-info">User Management</span>
    </div>
  </div>
  <div class="auth-right" style="justify-content:center;align-items:center;gap:1.5rem;display:flex;flex-direction:column;">
    <div style="max-width:400px;width:100%;">
      <div class="auth-title" style="text-align:center;margin-bottom:.5rem;">Welcome to AdminHub Pro</div>
      <div class="auth-sub2" style="text-align:center;margin-bottom:2rem;">Choose your portal to continue</div>
      <a href="?action=admin_login" class="card flex gap-2 center" style="margin-bottom:1rem;padding:1.4rem 1.8rem;text-decoration:none;transition:.2s;border-color:rgba(245,200,66,.2);" onmouseover="this.style.borderColor='var(--gold)'" onmouseout="this.style.borderColor='rgba(245,200,66,.2)'">
        <div class="avatar-placeholder md" style="background:linear-gradient(135deg,#f5c842,#e5a800);">👑</div>
        <div>
          <div class="bold" style="font-family:var(--font-head);font-size:16px;color:var(--gold);">Admin Portal</div>
          <div class="small">Manage users, verify payments</div>
        </div>
        <span class="tag tag-admin" style="margin-left:auto;">Admin</span>
      </a>
      <a href="?action=user_login" class="card flex gap-2 center" style="padding:1.4rem 1.8rem;text-decoration:none;transition:.2s;" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
        <div class="avatar-placeholder md">👤</div>
        <div>
          <div class="bold" style="font-family:var(--font-head);font-size:16px;color:var(--accent);">User Portal</div>
          <div class="small">Register, login, manage account</div>
        </div>
        <span class="tag tag-user" style="margin-left:auto;">User</span>
      </a>
      <div style="text-align:center;margin-top:1.5rem;font-size:13px;color:var(--muted);">
        New here? <a href="?action=user_register">Create a user account →</a>
      </div>
    </div>
  </div>
</div>

<?php // ══════════════ ADMIN LOGIN ══════════════
elseif ($action === 'admin_login'): ?>
<div class="auth-wrap">
  <div class="auth-left">
    <div class="auth-logo">
      <svg width="32" height="32" viewBox="0 0 32 32"><rect width="32" height="32" rx="8" fill="#f5c842" opacity=".15"/><path d="M16 8l6 4v8l-6 4-6-4v-8z" stroke="#f5c842" stroke-width="1.5" fill="none"/><circle cx="16" cy="16" r="3" fill="#f5c842"/></svg>
      Admin<span style="color:var(--gold);">Panel</span>
    </div>
    <div class="auth-tagline">Admin<br><em style="color:var(--gold);">Control Center</em></div>
    <p class="auth-sub">Secure administrative access to manage users, verify payments, and oversee the platform.</p>
    <div class="mt-3 small">
      <div style="margin-bottom:.3rem;">📧 Default: admin@system.com</div>
      <div>🔑 Default: Admin@1234</div>
    </div>
  </div>
  <div class="auth-right">
    <div style="max-width:420px;width:100%;">
      <?php if ($flash): ?>
      <div class="flash flash-<?= $flash['type'] ?>">
        <?= $flash['type'] === 'success' ? '✓' : '✕' ?> <?= sanitize($flash['msg']) ?>
      </div>
      <?php endif; ?>
      <div class="tag tag-admin mb-2">Administrator</div>
      <div class="auth-title">Sign In</div>
      <div class="auth-sub2">Access the admin dashboard</div>
      <form method="POST" action="?action=admin_login">
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-control" placeholder="admin@example.com" required>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn btn-lg w100" style="background:linear-gradient(135deg,#f5c842,#e5a800);color:#0a0d14;font-family:var(--font-head);font-weight:800;margin-top:.5rem;">
          Sign In as Admin
        </button>
      </form>
      <div class="auth-switch"><a href="?action=home">← Back to Home</a></div>
    </div>
  </div>
</div>

<?php // ══════════════ USER LOGIN ══════════════
elseif ($action === 'user_login'): ?>
<div class="auth-wrap">
  <div class="auth-left">
    <div class="auth-logo">
      <svg width="32" height="32" viewBox="0 0 32 32"><rect width="32" height="32" rx="8" fill="#4f8cff" opacity=".15"/><circle cx="16" cy="13" r="5" stroke="#4f8cff" stroke-width="1.5" fill="none"/><path d="M7 26c0-5 4-8 9-8s9 3 9 8" stroke="#4f8cff" stroke-width="1.5" fill="none" stroke-linecap="round"/></svg>
      Admin<span>Hub</span> Pro
    </div>
    <div class="auth-tagline">User<br><em>Dashboard</em></div>
    <p class="auth-sub">Access your account, view payment status, upload proof of payment, and manage your profile.</p>
  </div>
  <div class="auth-right">
    <div style="max-width:420px;width:100%;">
      <?php if ($flash): ?>
      <div class="flash flash-<?= $flash['type'] ?>">
        <?= $flash['type'] === 'success' ? '✓' : '✕' ?> <?= sanitize($flash['msg']) ?>
      </div>
      <?php endif; ?>
      <div class="tag tag-user mb-2">User Portal</div>
      <div class="auth-title">Welcome Back</div>
      <div class="auth-sub2">Sign in to your account</div>
      <form method="POST" action="?action=user_login">
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn btn-primary btn-lg w100" style="font-family:var(--font-head);font-weight:800;margin-top:.5rem;">
          Sign In
        </button>
      </form>
      <div class="divider">or</div>
      <a href="?action=user_register" class="btn btn-ghost btn-lg w100" style="justify-content:center;">Create New Account</a>
      <div class="auth-switch"><a href="?action=home">← Back to Home</a></div>
    </div>
  </div>
</div>

<?php // ══════════════ USER REGISTER ══════════════
elseif ($action === 'user_register'): ?>
<div style="min-height:100vh;background:var(--bg);padding:2rem 1rem;display:flex;justify-content:center;align-items:flex-start;">
  <div style="max-width:700px;width:100%;padding-top:1rem;">
    <div class="flex between mb-3">
      <div>
        <div class="flex gap-1 center mb-1">
          <div class="tag tag-user">New Account</div>
        </div>
        <div class="section-title" style="margin-bottom:0;">Create Your Account</div>
        <div class="small">Join the platform — fill in all details below</div>
      </div>
      <a href="?action=user_login" class="btn btn-ghost btn-sm">Sign In Instead</a>
    </div>
    <?php if ($flash): ?>
    <div class="flash flash-<?= $flash['type'] ?>">
      <?= $flash['type'] === 'success' ? '✓' : '✕' ?> <?= sanitize($flash['msg']) ?>
    </div>
    <?php endif; ?>
    <div class="card">
      <form method="POST" action="?action=user_register" enctype="multipart/form-data">
        <div class="form-2col">
          <div class="form-group">
            <label class="form-label">Full Name *</label>
            <input type="text" name="full_name" class="form-control" placeholder="John Doe" required>
          </div>
          <div class="form-group">
            <label class="form-label">Email Address *</label>
            <input type="email" name="email" class="form-control" placeholder="john@example.com" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Phone Number *</label>
          <div class="phone-row">
            <select name="country_code" id="cc_select" class="form-control cc-select" required onchange="updatePhoneHint(this)">
              <?php foreach ($country_codes as $cc): ?>
              <option value="<?= $cc['code'] ?>"
                data-country="<?= htmlspecialchars($cc['country']) ?>"
                data-pattern="<?= htmlspecialchars($cc['pattern']) ?>">
                <?= $cc['flag'] ?> <?= $cc['code'] ?> <?= $cc['country'] ?>
              </option>
              <?php endforeach; ?>
            </select>
            <input type="hidden" name="country" id="reg_country" value="United States">
            <input type="tel" name="phone" id="phone_input" class="form-control" placeholder="(555) 123-4567" required>
          </div>
          <div class="input-hint" id="phone_hint">Format: (###) ###-####</div>
        </div>
        <div class="form-group">
          <label class="form-label">Address</label>
          <input type="text" name="address" class="form-control" placeholder="123 Street, City, State">
        </div>
        <div class="form-2col">
          <div class="form-group">
            <label class="form-label">Password *</label>
            <input type="password" name="password" id="reg_pass" class="form-control" placeholder="Min. 6 characters" required minlength="6">
          </div>
          <div class="form-group">
            <label class="form-label">Confirm Password *</label>
            <input type="password" name="password_confirm" id="reg_pass2" class="form-control" placeholder="Repeat password" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Profile Photo (optional)</label>
          <input type="file" name="avatar" class="form-control" accept="image/*" style="padding:.5rem;">
        </div>
        <div style="padding:.9rem;background:rgba(245,200,66,.07);border:1px solid rgba(245,200,66,.2);border-radius:var(--r);margin-bottom:1.2rem;font-size:13px;">
          🏦 <strong>Payment Info:</strong> After registering, you can upload a payment screenshot in your dashboard. CBE Account: <span class="mono" style="color:var(--gold);">1000297043858</span>
        </div>
        <button type="submit" class="btn btn-primary btn-lg w100" onclick="return validateRegForm()">
          Create Account
        </button>
      </form>
    </div>
    <div class="auth-switch" style="margin-top:1rem;">Already have an account? <a href="?action=user_login">Sign in →</a></div>
  </div>
</div>

<?php // ══════════════ ADMIN DASHBOARD ══════════════
elseif (in_array($action, ['admin_dashboard', 'admin_users', 'admin_profile']) && is_admin()):

  $page_title = match($action) {
    'admin_dashboard' => 'Dashboard',
    'admin_users'     => 'User Management',
    'admin_profile'   => 'My Profile',
    default           => 'Admin'
  };
?>
<div class="layout">
  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <div class="dot"></div>
      AdminHub <span style="color:var(--muted);font-weight:400;">Pro</span>
      <button class="hamburger" style="margin-left:auto;" onclick="toggleSidebar()">✕</button>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section">Main</div>
      <a href="?action=admin_dashboard" class="nav-item <?= $action==='admin_dashboard'?'active':'' ?>">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        Dashboard
      </a>
      <a href="?action=admin_users" class="nav-item <?= $action==='admin_users'?'active':'' ?>">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="9" cy="7" r="4"/><path d="M2 20c0-4 3-7 7-7h4c4 0 7 3 7 7"/><circle cx="19" cy="8" r="2.5"/></svg>
        Users
        <span class="badge badge-info" style="margin-left:auto;font-size:11px;"><?= $stats['total'] ?></span>
      </a>
      <div class="nav-section" style="margin-top:.5rem;">Account</div>
      <a href="?action=admin_profile" class="nav-item <?= $action==='admin_profile'?'active':'' ?>">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
        My Profile
      </a>
      <a href="?action=admin_logout" class="nav-item" onclick="return confirm('Log out?')">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
        Logout
      </a>
    </nav>
    <div class="sidebar-foot">
      <div class="sidebar-user">
        <?php if ($current_admin['avatar']): ?>
        <img src="<?= thumb($current_admin['avatar']) ?>" class="avatar avatar-sm">
        <?php else: ?>
        <div class="avatar-placeholder sm"><?= strtoupper(substr($current_admin['name'],0,1)) ?></div>
        <?php endif; ?>
        <div class="sidebar-user-info">
          <div class="sidebar-user-name"><?= sanitize($current_admin['name']) ?></div>
          <div class="sidebar-user-role" style="color:var(--gold);">Administrator</div>
        </div>
      </div>
    </div>
  </aside>

  <!-- Main -->
  <main class="main-content">
    <header class="topbar">
      <div class="flex gap-2 center">
        <button class="hamburger" onclick="toggleSidebar()">
          <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
        </button>
        <div class="topbar-title"><?= $page_title ?></div>
      </div>
      <div class="flex gap-2 center">
        <span class="small"><?= date('D, M j, Y') ?></span>
        <a href="?action=admin_profile" class="flex gap-1 center" style="text-decoration:none;">
          <?php if ($current_admin['avatar']): ?>
          <img src="<?= thumb($current_admin['avatar']) ?>" class="avatar avatar-sm">
          <?php else: ?>
          <div class="avatar-placeholder sm"><?= strtoupper(substr($current_admin['name'],0,1)) ?></div>
          <?php endif; ?>
          <span class="bold" style="font-size:14px;"><?= sanitize($current_admin['name']) ?></span>
        </a>
      </div>
    </header>

    <div class="page-content">
      <?php if ($flash): ?>
      <div class="flash flash-<?= $flash['type'] ?>">
        <?= $flash['type'] === 'success' ? '✓' : '✕' ?> <?= sanitize($flash['msg']) ?>
      </div>
      <?php endif; ?>

      <?php if ($action === 'admin_dashboard'): ?>
      <!-- ── VILLA WELCOME BANNER ── -->
      <div class="villa-banner">
        <div class="villa-icon">🏡</div>
        <div class="villa-text-wrap">
          <div class="villa-intro">Welcome to our website — Admin Overview</div>
          <div class="villa-headline">
            Get your
            <span class="villa-highlight">Villa house in Addis Ababa</span>
            today!
          </div>
          <div class="villa-deposit">🏦 By depositing <strong>1,000 ETB</strong> to the CBE account provided &nbsp;·&nbsp; Monitor payments below</div>
        </div>
        <div class="villa-badge">
          <span class="villa-badge-amount">1,000</span>
          <span class="villa-badge-label">ETB Deposit</span>
        </div>
      </div>

      <!-- ── LOTTERY BANNER (Admin) ── -->
      <div class="lottery-banner" style="margin-bottom:1.5rem;">
        <div class="lottery-trophy">🏆</div>
        <div class="lottery-text">
          <div class="lottery-intro">🎟️ Ethiopia Lottery Platform</div>
          <div class="lottery-headline">
            <span class="lott-hl">Lottery Website</span>
            &nbsp;— Get Different Rewards in Addis Ababa, Ethiopia
          </div>
          <div style="font-size:13px;color:rgba(200,180,255,.65);margin-top:.3rem;">
            🏠 House &nbsp;·&nbsp; 🚗 Vehicles &nbsp;·&nbsp; 💵 Money &nbsp;·&nbsp; 📺 Electric Devices &nbsp;·&nbsp; 🏗️ Land for Construction &nbsp;·&nbsp; <em>and more…</em>
          </div>
          <div class="rewards-ticker" style="margin-top:.6rem;">
            <span class="ticker-label">🎁 Rewards:</span>
            <div class="ticker-track">
              <span class="ticker-item">🏠 House</span>
              <span class="ticker-item">🚗 Vehicles</span>
              <span class="ticker-item">💵 Cash Money</span>
              <span class="ticker-item">📺 Electric Devices</span>
              <span class="ticker-item">🏗️ Land for Construction</span>
              <span class="ticker-item">🏠 House</span>
              <span class="ticker-item">🚗 Vehicles</span>
              <span class="ticker-item">💵 Cash Money</span>
              <span class="ticker-item">📺 Electric Devices</span>
              <span class="ticker-item">🏗️ Land for Construction</span>
            </div>
          </div>
        </div>
        <div style="z-index:1;flex-shrink:0;text-align:center;">
          <div style="background:rgba(160,100,255,.15);border:1.5px solid rgba(160,100,255,.3);border-radius:var(--r);padding:.7rem 1.2rem;">
            <span style="font-family:var(--font-head);font-size:28px;font-weight:800;color:#c084fc;display:block;animation:countPop 1.5s ease-in-out infinite alternate;"><?= $stats['lottery'] ?></span>
            <span style="font-size:11px;color:rgba(200,170,255,.6);text-transform:uppercase;letter-spacing:.07em;">Numbers Issued</span>
          </div>
        </div>
      </div>

      <!-- ── STATS ── -->
      <div class="stats-grid">
        <div class="stat-card stat-blue">
          <div class="stat-icon">👥</div>
          <div class="stat-label">Total Users</div>
          <div class="stat-value"><?= $stats['total'] ?></div>
        </div>
        <div class="stat-card stat-yellow">
          <div class="stat-icon">⏳</div>
          <div class="stat-label">Pending</div>
          <div class="stat-value"><?= $stats['pending'] ?></div>
        </div>
        <div class="stat-card stat-green">
          <div class="stat-icon">✓</div>
          <div class="stat-label">Approved</div>
          <div class="stat-value"><?= $stats['approved'] ?></div>
        </div>
        <div class="stat-card stat-red">
          <div class="stat-icon">✕</div>
          <div class="stat-label">Rejected</div>
          <div class="stat-value"><?= $stats['rejected'] ?></div>
        </div>
        <div class="stat-card" style="background:linear-gradient(135deg,#160d30,#1e1040);border-color:rgba(160,100,255,.25);">
          <div class="stat-icon">🎟️</div>
          <div class="stat-label" style="color:rgba(200,170,255,.7);">Lottery Assigned</div>
          <div class="stat-value" style="color:#c084fc;"><?= $stats['lottery'] ?></div>
        </div>
      </div>

      <!-- Recent Users -->
      <div class="card">
        <div class="card-header">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="9" cy="7" r="4"/><path d="M2 20c0-4 3-7 7-7h4"/><circle cx="18" cy="16" r="4"/><path d="M18 13v3l2 2"/></svg>
          Recent Registrations
          <a href="?action=admin_users" class="btn btn-ghost btn-sm" style="margin-left:auto;">View All</a>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr>
              <th>User</th><th>Country</th><th>Phone</th><th>Registered</th><th>Payment</th>
            </tr></thead>
            <tbody>
              <?php $recent = array_slice($users_list, 0, 6); ?>
              <?php foreach ($recent as $u): ?>
              <tr>
                <td>
                  <div class="flex gap-1 center">
                    <?php if ($u['avatar']): ?>
                    <img src="<?= thumb($u['avatar']) ?>" class="avatar avatar-sm">
                    <?php else: ?>
                    <div class="avatar-placeholder sm"><?= strtoupper(substr($u['full_name'],0,1)) ?></div>
                    <?php endif; ?>
                    <div>
                      <div class="bold" style="font-size:13px;"><?= sanitize($u['full_name']) ?></div>
                      <div class="small"><?= sanitize($u['email']) ?></div>
                    </div>
                  </div>
                </td>
                <td><?= sanitize($u['country']) ?></td>
                <td class="mono" style="font-size:13px;"><?= sanitize($u['country_code']) ?> <?= sanitize($u['phone']) ?></td>
                <td class="small"><?= date('M j, Y', strtotime($u['registered_at'])) ?></td>
                <td><span class="badge badge-<?= $u['payment_status'] ?>"><?= $u['payment_status'] ?></span></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($recent)): ?>
              <tr><td colspan="5" class="empty-state">No users registered yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php elseif ($action === 'admin_users'): ?>
      <!-- ── USER MANAGEMENT ── -->
      <div style="margin-bottom:1rem;display:flex;gap:1rem;align-items:center;flex-wrap:wrap;">
        <div class="section-title" style="margin:0;">All Users</div>
        <div class="flex gap-1 center" style="margin-left:auto;flex-wrap:wrap;">
          <input type="text" id="search_users" class="form-control" placeholder="Search users..." style="width:220px;" oninput="filterUsers()">
          <select id="filter_status" class="form-control" style="width:150px;" onchange="filterUsers()">
            <option value="">All Status</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>
      </div>
      <div class="card" style="padding:0;overflow:hidden;">
        <div class="table-wrap">
          <table id="users_table">
            <thead><tr>
              <th>#</th><th>User</th><th>Country / Phone</th><th>Address</th><th>Registered</th><th>Payment</th><th>Screenshot</th><th>Lottery #</th><th>Actions</th>
            </tr></thead>
            <tbody>
              <?php foreach ($users_list as $i => $u): ?>
              <tr class="user-row"
                data-name="<?= strtolower($u['full_name']) ?>"
                data-email="<?= strtolower($u['email']) ?>"
                data-status="<?= $u['payment_status'] ?>">
                <td class="small"><?= $i+1 ?></td>
                <td>
                  <div class="flex gap-1 center">
                    <?php if ($u['avatar']): ?>
                    <img src="<?= thumb($u['avatar']) ?>" class="avatar avatar-sm">
                    <?php else: ?>
                    <div class="avatar-placeholder sm"><?= strtoupper(substr($u['full_name'],0,1)) ?></div>
                    <?php endif; ?>
                    <div>
                      <div class="bold" style="font-size:13px;"><?= sanitize($u['full_name']) ?></div>
                      <div class="small"><?= sanitize($u['email']) ?></div>
                    </div>
                  </div>
                </td>
                <td>
                  <div style="font-size:13px;"><?= sanitize($u['country']) ?></div>
                  <div class="small mono"><?= sanitize($u['country_code']) ?> <?= sanitize($u['phone']) ?></div>
                </td>
                <td class="small"><?= $u['address'] ? sanitize($u['address']) : '—' ?></td>
                <td>
                  <div style="font-size:13px;"><?= date('M j, Y', strtotime($u['registered_at'])) ?></div>
                  <?php if ($u['last_login']): ?>
                  <div class="small">Last: <?= date('M j', strtotime($u['last_login'])) ?></div>
                  <?php endif; ?>
                </td>
                <td><span class="badge badge-<?= $u['payment_status'] ?>"><?= $u['payment_status'] ?></span></td>
                <td>
                  <?php if ($u['payment_screenshot']): ?>
                  <img src="<?= thumb($u['payment_screenshot']) ?>" class="payment-thumb"
                    onclick="viewImage('<?= thumb($u['payment_screenshot']) ?>')"
                    title="Click to enlarge">
                  <?php else: ?>
                  <span class="small" style="color:var(--border2);">No upload</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($u['lottery_number']): ?>
                  <div>
                    <div class="lnum"><?= sanitize($u['lottery_number']) ?></div>
                    <?php if ($u['lottery_assigned_at']): ?>
                    <div class="small" style="color:rgba(74,222,128,.5);margin-top:.2rem;"><?= date('M j, Y', strtotime($u['lottery_assigned_at'])) ?></div>
                    <?php endif; ?>
                  </div>
                  <?php else: ?>
                  <span class="small" style="color:var(--border2);">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="flex gap-1 wrap">
                    <form method="POST" action="?action=update_payment" style="display:inline;">
                      <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                      <input type="hidden" name="status" value="approved">
                      <button type="submit" class="btn btn-success btn-sm" title="Approve"
                        onclick="return confirm('Approve payment for <?= sanitize($u['full_name']) ?>?')">✓ Approve</button>
                    </form>
                    <form method="POST" action="?action=update_payment" style="display:inline;">
                      <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                      <input type="hidden" name="status" value="rejected">
                      <button type="submit" class="btn btn-danger btn-sm" title="Reject"
                        onclick="return confirm('Reject payment?')">✕ Reject</button>
                    </form>
                    <?php if ($u['payment_status'] === 'approved'): ?>
                    <form method="POST" action="?action=assign_lottery" style="display:inline;">
                      <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                      <button type="submit" class="btn btn-lottery btn-sm" title="Assign Lottery Number"
                        onclick="return confirm('Assign a random 6-digit lottery number to <?= sanitize($u['full_name']) ?>?')">
                        🎟️ <?= $u['lottery_number'] ? 'Re-assign' : 'Assign #' ?>
                      </button>
                    </form>
                    <?php if ($u['lottery_number']): ?>
                    <form method="POST" action="?action=revoke_lottery" style="display:inline;">
                      <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                      <button type="submit" class="btn btn-ghost btn-sm" style="border-color:rgba(74,222,128,.3);color:rgba(74,222,128,.6);"
                        onclick="return confirm('Revoke lottery number?')">✕ Revoke</button>
                    </form>
                    <?php endif; ?>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($users_list)): ?>
              <tr><td colspan="9">
                <div class="empty-state">
                  <div class="empty-state-icon">👥</div>
                  <div>No users registered yet.</div>
                </div>
              </td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php elseif ($action === 'admin_profile'): ?>
      <!-- ── ADMIN PROFILE ── -->
      <div class="profile-grid">
        <div class="card profile-card">
          <?php if ($current_admin['avatar']): ?>
          <img src="<?= thumb($current_admin['avatar']) ?>" class="avatar avatar-lg" style="margin:0 auto;">
          <?php else: ?>
          <div class="avatar-placeholder lg" style="margin:0 auto;"><?= strtoupper(substr($current_admin['name'],0,1)) ?></div>
          <?php endif; ?>
          <div class="profile-name"><?= sanitize($current_admin['name']) ?></div>
          <div class="profile-email"><?= sanitize($current_admin['email']) ?></div>
          <div style="margin-top:.7rem;"><span class="tag tag-admin">Super Admin</span></div>
          <div class="profile-meta">
            <div class="profile-meta-row">
              <span class="small">Member Since</span>
              <span style="font-size:13px;"><?= date('M j, Y', strtotime($current_admin['created_at'])) ?></span>
            </div>
            <div class="profile-meta-row">
              <span class="small">Total Users</span>
              <span style="font-size:13px;color:var(--accent);"><?= $stats['total'] ?></span>
            </div>
          </div>
        </div>
        <div class="card">
          <div class="card-header">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 013 3L12 15l-4 1 1-4z"/></svg>
            Update Profile
          </div>
          <form method="POST" action="?action=admin_update_profile" enctype="multipart/form-data">
            <div class="form-2col">
              <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" name="name" class="form-control" value="<?= sanitize($current_admin['name']) ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" value="<?= sanitize($current_admin['email']) ?>" required>
              </div>
            </div>
            <div class="form-2col">
              <div class="form-group">
                <label class="form-label">New Password</label>
                <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current">
              </div>
              <div class="form-group">
                <label class="form-label">Profile Photo</label>
                <input type="file" name="avatar" class="form-control" accept="image/*" style="padding:.5rem;">
              </div>
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </form>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<?php // ══════════════ USER DASHBOARD ══════════════
elseif ($action === 'user_dashboard' && is_user()):
  $u = $current_user;
?>
<div class="layout">
  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <div class="dot"></div>
      My<span style="color:var(--accent);">Account</span>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section">Navigation</div>
      <a href="#dashboard" class="nav-item active" onclick="showTab('dashboard',this)">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        Dashboard
      </a>
      <a href="#payment" class="nav-item" onclick="showTab('payment',this)">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
        Payment
        <?php if ($u['payment_status'] === 'pending' && $u['payment_screenshot']): ?>
        <span class="badge badge-pending" style="margin-left:auto;font-size:10px;">!</span>
        <?php endif; ?>
      </a>
      <a href="#lottery" class="nav-item" onclick="showTab('lottery',this)" style="<?= $u['lottery_number'] ? 'color:var(--success);' : '' ?>">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-5"/></svg>
        My Lottery #
        <?php if ($u['lottery_number']): ?>
        <span class="badge badge-approved" style="margin-left:auto;font-size:10px;">✓</span>
        <?php endif; ?>
      </a>
      <a href="#profile" class="nav-item" onclick="showTab('profile',this)">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
        My Profile
      </a>
      <a href="?action=user_logout" class="nav-item" onclick="return confirm('Log out?')">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
        Logout
      </a>
    </nav>
    <div class="sidebar-foot">
      <div class="sidebar-user">
        <?php if ($u['avatar']): ?>
        <img src="<?= thumb($u['avatar']) ?>" class="avatar avatar-sm">
        <?php else: ?>
        <div class="avatar-placeholder sm"><?= strtoupper(substr($u['full_name'],0,1)) ?></div>
        <?php endif; ?>
        <div class="sidebar-user-info">
          <div class="sidebar-user-name"><?= sanitize($u['full_name']) ?></div>
          <div class="sidebar-user-role">User Account</div>
        </div>
      </div>
    </div>
  </aside>

  <main class="main-content">
    <header class="topbar">
      <div class="flex gap-2 center">
        <button class="hamburger" onclick="toggleSidebar()">
          <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
        </button>
        <div class="topbar-title" id="topbar-title">Dashboard</div>
      </div>
      <div class="flex gap-2 center">
        <span class="badge badge-<?= $u['payment_status'] ?>"><?= $u['payment_status'] ?></span>
        <span class="small" style="display:none;" id="desktop_name"><?= sanitize($u['full_name']) ?></span>
      </div>
    </header>
    <div class="page-content">
      <?php if ($flash): ?>
      <div class="flash flash-<?= $flash['type'] ?>">
        <?= $flash['type'] === 'success' ? '✓' : '✕' ?> <?= sanitize($flash['msg']) ?>
      </div>
      <?php endif; ?>

      <!-- ── TAB: DASHBOARD ── -->
      <div class="tab-panel active" id="tab-dashboard">
        <!-- Villa Welcome Banner -->
        <div class="villa-banner">
          <div class="villa-icon">🏡</div>
          <div class="villa-text-wrap">
            <div class="villa-intro">Welcome to our website, <?= sanitize($u['full_name']) ?>!</div>
            <div class="villa-headline">
              Get your
              <span class="villa-highlight">Villa house in Addis Ababa</span>
            </div>
            <div class="villa-deposit">🏦 By depositing <strong>1,000 ETB</strong> to the CBE account provided below</div>
          </div>
          <div class="villa-badge">
            <span class="villa-badge-amount">1,000</span>
            <span class="villa-badge-label">ETB Deposit</span>
          </div>
        </div>

        <!-- Lottery Banner -->
        <div class="lottery-banner" style="margin-bottom:1.2rem;">
          <div class="lottery-trophy">🏆</div>
          <div class="lottery-text">
            <div class="lottery-intro">🎟️ Addis Ababa, Ethiopia — Lucky Draw</div>
            <div class="lottery-headline">
              <span class="lott-hl">Lottery Website</span>
              &nbsp;to get different rewards
            </div>
            <div style="font-size:13px;color:rgba(200,180,255,.65);line-height:1.7;margin-top:.3rem;">
              in <strong style="color:#fff;">Addis Ababa, Ethiopia</strong> such as:
              <strong style="color:#e2c9ff;">House</strong>,
              <strong style="color:#e2c9ff;">Vehicles</strong>,
              <strong style="color:#e2c9ff;">Money</strong>,
              <strong style="color:#e2c9ff;">Electric Devices</strong>,
              <strong style="color:#e2c9ff;">Land for Construction</strong>, etc.
            </div>
            <div class="rewards-ticker">
              <span class="ticker-label">🎁 Prizes:</span>
              <div class="ticker-track">
                <span class="ticker-item">🏠 House</span>
                <span class="ticker-item">🚗 Vehicles</span>
                <span class="ticker-item">💵 Cash Money</span>
                <span class="ticker-item">📺 Electric Devices</span>
                <span class="ticker-item">🏗️ Land for Construction</span>
                <span class="ticker-item">🏠 House</span>
                <span class="ticker-item">🚗 Vehicles</span>
                <span class="ticker-item">💵 Cash Money</span>
                <span class="ticker-item">📺 Electric Devices</span>
                <span class="ticker-item">🏗️ Land for Construction</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Lottery Ticket Number -->
        <?php if ($u['lottery_number']): ?>
        <div class="lottery-ticket">
          <div class="lt-label">🎟️ Your Lottery Number</div>
          <div class="lt-number-wrap">
            <span class="lt-number"><?= sanitize($u['lottery_number']) ?></span>
          </div>
          <div class="lt-congrats">🎉 Congratulations! Your lottery number has been assigned. Good luck!</div>
          <?php if ($u['lottery_assigned_at']): ?>
          <div class="lt-date">Assigned on <?= date('F j, Y \a\t g:i A', strtotime($u['lottery_assigned_at'])) ?></div>
          <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="lt-none">
          <div class="lt-none-icon">🎫</div>
          <div class="bold" style="margin-bottom:.3rem;color:var(--text);">No Lottery Number Yet</div>
          <div class="small">
            <?php if ($u['payment_status'] === 'approved'): ?>
              Your payment is approved! The admin will assign your lucky number soon.
            <?php elseif ($u['payment_status'] === 'pending'): ?>
              Complete your payment and wait for approval to receive your lottery number.
            <?php else: ?>
              Your payment was rejected. Please re-upload your screenshot to proceed.
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- CBE Account Banner -->
        <div class="cbe-banner">
          <div class="cbe-info">
            <div class="cbe-label">💳 Payment Destination — CBE Bank</div>
            <div class="cbe-account">1000297043858</div>
            <div class="cbe-bank">Commercial Bank of Ethiopia (CBE) — Transfer & Deposit</div>
          </div>
          <div>
            <div class="cbe-badge">Active Account</div>
            <div class="small mt-1" style="text-align:right;">Upload screenshot after payment</div>
          </div>
        </div>

        <!-- Welcome & Status -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.5rem;">
          <div class="card">
            <div class="small mb-1">Welcome back,</div>
            <div style="font-family:var(--font-head);font-size:22px;font-weight:800;"><?= sanitize($u['full_name']) ?></div>
            <div class="small mt-1"><?= sanitize($u['email']) ?></div>
            <div class="flex gap-1 mt-2 center">
              <span class="small">📍</span>
              <span style="font-size:13px;"><?= sanitize($u['country_code']) ?> — <?= sanitize($u['country']) ?></span>
            </div>
          </div>
          <div class="card" style="text-align:center;">
            <div class="small mb-1">Payment Status</div>
            <?php
              $status_icon = match($u['payment_status']) {
                'approved' => '✅', 'rejected' => '❌', default => '⏳'
              };
            ?>
            <div style="font-size:48px;"><?= $status_icon ?></div>
            <span class="badge badge-<?= $u['payment_status'] ?>" style="font-size:14px;padding:.4rem 1.2rem;">
              <?= strtoupper($u['payment_status']) ?>
            </span>
            <?php if ($u['payment_status'] === 'rejected'): ?>
            <div class="small mt-2" style="color:var(--danger);">Please re-upload your payment proof.</div>
            <?php elseif ($u['payment_status'] === 'pending' && !$u['payment_screenshot']): ?>
            <div class="small mt-2">Upload your payment screenshot to proceed.</div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Account Info -->
        <div class="card">
          <div class="card-header">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
            Account Details
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;">
            <?php $meta = [
              ['📧 Email', $u['email']],
              ['📱 Phone', $u['country_code'] . ' ' . $u['phone']],
              ['🌍 Country', $u['country']],
              ['🏠 Address', $u['address'] ?: '—'],
              ['📅 Registered', date('F j, Y', strtotime($u['registered_at']))],
              ['🕐 Last Login', $u['last_login'] ? date('M j, Y g:i A', strtotime($u['last_login'])) : '—'],
            ]; ?>
            <?php foreach ($meta as [$label, $val]): ?>
            <div style="padding:.6rem;background:var(--surface2);border-radius:var(--r);font-size:13px;">
              <div class="small" style="margin-bottom:.2rem;"><?= $label ?></div>
              <div class="bold"><?= sanitize((string)$val) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- ── TAB: PAYMENT ── -->
      <div class="tab-panel" id="tab-payment">
        <!-- CBE Banner -->
        <div class="cbe-banner">
          <div class="cbe-info">
            <div class="cbe-label">💳 Send Payment To</div>
            <div class="cbe-account">1000297043858</div>
            <div class="cbe-bank">Commercial Bank of Ethiopia (CBE)</div>
          </div>
          <button class="btn btn-ghost btn-sm" onclick="copyAccount()" id="copy_btn">📋 Copy Account</button>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;flex-wrap:wrap;">
          <!-- Upload -->
          <div class="card">
            <div class="card-header">
              <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
              Upload Payment Screenshot
            </div>
            <form method="POST" action="?action=upload_payment" enctype="multipart/form-data">
              <div class="upload-zone" onclick="document.getElementById('pay_file').click()">
                <div class="upload-zone-icon">📸</div>
                <div class="bold" style="margin-bottom:.3rem;">Click to upload screenshot</div>
                <div class="upload-zone-text">JPG, PNG, PDF — Max 5MB</div>
                <div id="file_name" class="small mt-1"></div>
              </div>
              <input type="file" name="payment" id="pay_file" style="display:none;" accept=".jpg,.jpeg,.png,.gif,.pdf"
                onchange="document.getElementById('file_name').textContent = this.files[0]?.name || ''">
              <button type="submit" class="btn btn-primary w100 mt-2">Upload & Submit</button>
            </form>
          </div>

          <!-- Current status -->
          <div class="card">
            <div class="card-header">📊 Payment Status</div>
            <?php if ($u['payment_screenshot']): ?>
            <div style="text-align:center;margin-bottom:1rem;">
              <img src="<?= thumb($u['payment_screenshot']) ?>"
                style="max-width:100%;max-height:200px;border-radius:var(--r);border:1px solid var(--border);cursor:pointer;"
                onclick="viewImage('<?= thumb($u['payment_screenshot']) ?>')"
                title="Click to enlarge">
            </div>
            <?php else: ?>
            <div class="empty-state" style="padding:2rem 0;">
              <div>📂</div>
              <div class="small mt-1">No screenshot uploaded yet</div>
            </div>
            <?php endif; ?>
            <div style="text-align:center;">
              <span class="badge badge-<?= $u['payment_status'] ?>" style="font-size:14px;padding:.5rem 1.5rem;">
                <?= strtoupper($u['payment_status']) ?>
              </span>
              <?php if ($u['payment_status'] === 'pending'): ?>
              <div class="small mt-2">Your screenshot is under review by admin.</div>
              <?php elseif ($u['payment_status'] === 'approved'): ?>
              <div class="small mt-2" style="color:var(--success);">Payment verified! ✅</div>
              <?php elseif ($u['payment_status'] === 'rejected'): ?>
              <div class="small mt-2" style="color:var(--danger);">Payment rejected. Please re-upload.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Instructions -->
        <div class="card mt-3" style="background:rgba(79,140,255,.05);border-color:rgba(79,140,255,.2);">
          <div class="card-header" style="color:var(--accent);">ℹ️ Payment Instructions</div>
          <ol style="padding-left:1.2rem;font-size:14px;line-height:2;color:var(--muted);">
            <li>Transfer the required amount to CBE Account: <strong style="color:var(--gold);font-family:monospace;">1000297043858</strong></li>
            <li>Take a clear screenshot of the transfer confirmation</li>
            <li>Upload the screenshot using the form above</li>
            <li>Wait for admin to verify and approve your payment</li>
            <li>You'll see your status update to "Approved" when done</li>
          </ol>
        </div>
      </div>

      <!-- ── TAB: LOTTERY ── -->
      <div class="tab-panel" id="tab-lottery">
        <!-- Big Lottery Banner -->
        <div class="lottery-banner" style="margin-bottom:1.5rem;">
          <div class="lottery-trophy" style="font-size:60px;">🏆</div>
          <div class="lottery-text">
            <div class="lottery-intro">🎟️ Ethiopia Lucky Draw — Addis Ababa</div>
            <div class="lottery-headline" style="font-size:24px;">
              <span class="lott-hl">Lottery Website</span>
              &nbsp;— Win Big Rewards!
            </div>
            <div style="font-size:14px;color:rgba(200,180,255,.7);margin-top:.5rem;line-height:1.9;">
              🏠 <strong style="color:#e2c9ff;">House</strong> &nbsp;·&nbsp;
              🚗 <strong style="color:#e2c9ff;">Vehicles</strong> &nbsp;·&nbsp;
              💵 <strong style="color:#e2c9ff;">Money</strong> &nbsp;·&nbsp;
              📺 <strong style="color:#e2c9ff;">Electric Devices</strong> &nbsp;·&nbsp;
              🏗️ <strong style="color:#e2c9ff;">Land for Construction</strong> &nbsp;·&nbsp; <em>etc.</em>
            </div>
            <div class="rewards-ticker" style="margin-top:.7rem;">
              <span class="ticker-label">🎁 Prizes:</span>
              <div class="ticker-track">
                <span class="ticker-item">🏠 House</span>
                <span class="ticker-item">🚗 Vehicles</span>
                <span class="ticker-item">💵 Cash Money</span>
                <span class="ticker-item">📺 Electric Devices</span>
                <span class="ticker-item">🏗️ Land for Construction</span>
                <span class="ticker-item">🏠 House</span>
                <span class="ticker-item">🚗 Vehicles</span>
                <span class="ticker-item">💵 Cash Money</span>
                <span class="ticker-item">📺 Electric Devices</span>
                <span class="ticker-item">🏗️ Land for Construction</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Lottery Number Display -->
        <?php if ($u['lottery_number']): ?>
        <div class="lottery-ticket" style="padding:2.5rem 2rem;">
          <div class="lt-label">🎟️ Your Personal Lottery Number</div>
          <div class="lt-number-wrap" style="display:block;margin:1.2rem auto;">
            <span class="lt-number" style="font-size:64px;display:block;text-align:center;"><?= sanitize($u['lottery_number']) ?></span>
          </div>
          <div class="lt-congrats" style="font-size:15px;">🎉 Congratulations, <?= sanitize($u['full_name']) ?>! Your lucky number has been issued. Keep it safe — this number will be used in the draw!</div>
          <?php if ($u['lottery_assigned_at']): ?>
          <div class="lt-date" style="margin-top:.6rem;font-size:13px;">📅 Assigned: <?= date('F j, Y \a\t g:i A', strtotime($u['lottery_assigned_at'])) ?></div>
          <?php endif; ?>
          <!-- Confetti dots -->
          <div style="display:flex;justify-content:center;gap:.5rem;margin-top:1.2rem;flex-wrap:wrap;">
            <?php foreach(['🏠','🚗','💵','📺','🏗️','🌟','🎊','🏡'] as $ic): ?>
            <span style="font-size:22px;animation:houseBounce <?= 1.5 + (rand(0,10)/10) ?>s ease-in-out infinite;animation-delay:<?= rand(0,8)/10 ?>s;"><?= $ic ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <?php else: ?>
        <div class="lt-none" style="padding:3rem;">
          <div class="lt-none-icon" style="font-size:60px;margin-bottom:1rem;">🎫</div>
          <div class="bold" style="font-size:18px;margin-bottom:.6rem;color:var(--text);">No Lottery Number Assigned Yet</div>
          <?php if ($u['payment_status'] === 'approved'): ?>
          <div class="small" style="font-size:14px;">✅ Your payment is <strong style="color:var(--success);">approved</strong>! The admin will assign your lucky 6-digit lottery number soon.</div>
          <?php elseif ($u['payment_status'] === 'pending'): ?>
          <div class="small" style="font-size:14px;">⏳ Please upload your <strong>payment screenshot</strong> and wait for admin approval to receive your lottery number.</div>
          <div class="mt-2"><a href="#payment" onclick="showTab('payment',document.querySelector('[onclick*=payment]'))" class="btn btn-primary btn-sm">Upload Payment →</a></div>
          <?php else: ?>
          <div class="small" style="font-size:14px;">❌ Your payment was <strong style="color:var(--danger);">rejected</strong>. Please re-upload your payment screenshot.</div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- ── TAB: PROFILE ── -->
      <div class="tab-panel" id="tab-profile">
        <div class="profile-grid">
          <div class="card profile-card">
            <?php if ($u['avatar']): ?>
            <img src="<?= thumb($u['avatar']) ?>" class="avatar avatar-lg" style="margin:0 auto;">
            <?php else: ?>
            <div class="avatar-placeholder lg" style="margin:0 auto;"><?= strtoupper(substr($u['full_name'],0,1)) ?></div>
            <?php endif; ?>
            <div class="profile-name"><?= sanitize($u['full_name']) ?></div>
            <div class="profile-email"><?= sanitize($u['email']) ?></div>
            <div class="mt-1"><span class="badge badge-<?= $u['payment_status'] ?>"><?= $u['payment_status'] ?></span></div>
            <div class="profile-meta">
              <div class="profile-meta-row">
                <span class="small">Registered</span>
                <span style="font-size:13px;"><?= date('M j, Y', strtotime($u['registered_at'])) ?></span>
              </div>
              <div class="profile-meta-row">
                <span class="small">Country</span>
                <span style="font-size:13px;"><?= sanitize($u['country']) ?></span>
              </div>
              <div class="profile-meta-row">
                <span class="small">Phone</span>
                <span class="mono" style="font-size:12px;"><?= sanitize($u['country_code']) ?> <?= sanitize($u['phone']) ?></span>
              </div>
            </div>
          </div>
          <div class="card">
            <div class="card-header">
              <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 013 3L12 15l-4 1 1-4z"/></svg>
              Profile is Read-Only
            </div>
            <div style="padding:1rem;background:var(--surface2);border-radius:var(--r);font-size:14px;color:var(--muted);">
              Your profile information was set during registration. Contact an administrator if you need to update your details.
            </div>
            <div style="margin-top:1.2rem;display:flex;flex-direction:column;gap:.5rem;">
              <?php $fields = [
                ['Full Name', $u['full_name']],
                ['Email', $u['email']],
                ['Phone', $u['country_code'] . ' ' . $u['phone']],
                ['Country', $u['country']],
                ['Address', $u['address'] ?: '—'],
              ]; ?>
              <?php foreach ($fields as [$label, $val]): ?>
              <div class="profile-meta-row">
                <span class="small"><?= $label ?></span>
                <span style="font-size:13px;"><?= sanitize((string)$val) ?></span>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>
<?php endif; ?>

<!-- ── IMAGE VIEWER MODAL ── -->
<div class="modal-bg" id="img_modal" onclick="this.classList.remove('open')">
  <div class="modal" style="max-width:700px;padding:1rem;" onclick="event.stopPropagation()">
    <div class="modal-header">
      <div class="modal-title">Payment Screenshot</div>
      <button class="modal-close" onclick="document.getElementById('img_modal').classList.remove('open')">×</button>
    </div>
    <img id="modal_img" src="" style="width:100%;border-radius:var(--r);max-height:70vh;object-fit:contain;">
  </div>
</div>

<script>
// ── Sidebar toggle
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}

// ── User tabs
function showTab(name, el) {
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  const panel = document.getElementById('tab-' + name);
  if (panel) panel.classList.add('active');
  if (el) el.classList.add('active');
  const titles = { dashboard: 'Dashboard', payment: 'Payment', profile: 'My Profile', lottery: 'My Lottery Number' };
  const t = document.getElementById('topbar-title');
  if (t) t.textContent = titles[name] || name;
  return false;
}

// ── Image viewer
function viewImage(src) {
  document.getElementById('modal_img').src = src;
  document.getElementById('img_modal').classList.add('open');
}

// ── Copy CBE account
function copyAccount() {
  navigator.clipboard.writeText('1000297043858').then(() => {
    const btn = document.getElementById('copy_btn');
    if (btn) { btn.textContent = '✓ Copied!'; setTimeout(() => btn.textContent = '📋 Copy Account', 2000); }
  });
}

// ── Filter users table (admin)
function filterUsers() {
  const q = document.getElementById('search_users')?.value.toLowerCase() || '';
  const s = document.getElementById('filter_status')?.value || '';
  document.querySelectorAll('.user-row').forEach(row => {
    const name  = row.dataset.name || '';
    const email = row.dataset.email || '';
    const stat  = row.dataset.status || '';
    const matchQ = !q || name.includes(q) || email.includes(q);
    const matchS = !s || stat === s;
    row.style.display = (matchQ && matchS) ? '' : 'none';
  });
}

// ── Register form validation
function validateRegForm() {
  const p1 = document.getElementById('reg_pass')?.value;
  const p2 = document.getElementById('reg_pass2')?.value;
  if (p1 !== p2) { alert('Passwords do not match!'); return false; }
  if (p1.length < 6) { alert('Password must be at least 6 characters.'); return false; }
  return true;
}

// ── Phone format hint
function updatePhoneHint(sel) {
  const opt = sel.options[sel.selectedIndex];
  const pattern = opt.dataset.pattern || '';
  const country = opt.dataset.country || '';
  const hint = document.getElementById('phone_hint');
  const reg_country = document.getElementById('reg_country');
  if (hint) hint.textContent = 'Format: ' + pattern;
  if (reg_country) reg_country.value = country;
  const ph = document.getElementById('phone_input');
  if (ph) ph.placeholder = pattern.replace(/#/g, '0');
}
// Init phone hint
const ccSel = document.getElementById('cc_select');
if (ccSel) updatePhoneHint(ccSel);

// Auto-hide flash
setTimeout(() => {
  document.querySelectorAll('.flash').forEach(f => {
    f.style.transition = 'opacity .5s';
    f.style.opacity = '0';
    setTimeout(() => f.remove(), 500);
  });
}, 4000);
</script>
</body>
</html>