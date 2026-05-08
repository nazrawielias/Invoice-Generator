<?php
session_start();

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'mydb';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    $error_message = 'Database connection failed: ' . $conn->connect_error;
    $conn = null;
} else {
    // Create table if not exists
    $create_table_sql = "
    CREATE TABLE IF NOT EXISTS business_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        business_name VARCHAR(255),
        business_no VARCHAR(100),
        first_name VARCHAR(100),
        last_name VARCHAR(100),
        telephone VARCHAR(50),
        mobile VARCHAR(50),
        address1 TEXT,
        address2 TEXT,
        city VARCHAR(100),
        province VARCHAR(100),
        country VARCHAR(100),
        next_invoice_no VARCHAR(50),
        taxes_data LONGTEXT,
        logo_path VARCHAR(500),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $conn->query($create_table_sql);
}

// Get existing settings
$existing_settings = [];
$taxes = [];
$logo_path = '';

$query = "SELECT * FROM business_settings ORDER BY id DESC LIMIT 1";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $existing_settings = $result->fetch_assoc();
    $logo_path = $existing_settings['logo_path'] ?? '';
    
    // Parse taxes
    if (!empty($existing_settings['taxes_data'])) {
        $taxes = json_decode($existing_settings['taxes_data'], true);
        if (!is_array($taxes)) {
            $taxes = [];
        }
    }
}

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    // Get form data
    $business_name = $_POST['business_name'] ?? '';
    $business_no = $_POST['business_no'] ?? '';
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $telephone = $_POST['telephone'] ?? '';
    $mobile = $_POST['mobile'] ?? '';
    $address1 = $_POST['address1'] ?? '';
    $address2 = $_POST['address2'] ?? '';
    $city = $_POST['city'] ?? '';
    $province = $_POST['province'] ?? '';
    $country = $_POST['country'] ?? '';
    $next_invoice_no = $_POST['next_invoice_no'] ?? '';
    $taxes_json = $_POST['taxes_json'] ?? '[]';
    
    // Handle logo upload
    $logo_path = '';
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $upload_dir = 'uploads/';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $filename = 'logo_' . time() . '.' . $file_extension;
        $logo_path = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $logo_path)) {
            // Success
        } else {
            $logo_path = $_POST['existing_logo'] ?? '';
        }
    } else {
        $logo_path = $_POST['existing_logo'] ?? '';
    }
    
    // Prepare SQL statement
    $sql = "INSERT INTO business_settings (
        business_name, business_no, first_name, last_name, 
        telephone, mobile, address1, address2, city, province, 
        country, next_invoice_no, taxes_data, logo_path
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param(
            "ssssssssssssss",
            $business_name,
            $business_no,
            $first_name,
            $last_name,
            $telephone,
            $mobile,
            $address1,
            $address2,
            $city,
            $province,
            $country,
            $next_invoice_no,
            $taxes_json,
            $logo_path
        );
        
        if ($stmt->execute()) {
            $message = '✅ Settings saved successfully!';
            $message_type = 'success';
            
            // Refresh the page to show updated data
            echo "<meta http-equiv='refresh' content='1'>";
        } else {
            $message = '❌ Failed to save settings: ' . $stmt->error;
            $message_type = 'error';
        }
        $stmt->close();
    } else {
        $message = '❌ Database error: ' . $conn->error;
        $message_type = 'error';
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <title>Account & Invoice Settings | Save to Database</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #eef2f7;
            font-family: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            padding: 2rem 1.5rem;
            color: #1a2a36;
        }

        .settings-container {
            max-width: 1280px;
            margin: 0 auto;
            background: white;
            border-radius: 28px;
            box-shadow: 0 20px 35px -12px rgba(0, 0, 0, 0.12);
            overflow: hidden;
        }

        .settings-header {
            background: #ffffff;
            padding: 1.6rem 2rem;
            border-bottom: 1px solid #eef2f8;
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .logo-area h1 {
            font-size: 1.85rem;
            font-weight: 700;
            letter-spacing: -0.3px;
            background: linear-gradient(135deg, #1f3b4c, #2c5a6e);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
        }

        .logo-area p {
            font-size: 0.8rem;
            color: #5b6f7e;
            margin-top: 4px;
        }

        .settings-badge {
            background: #f8fafc;
            padding: 0.45rem 1rem;
            border-radius: 60px;
            font-size: 0.85rem;
            font-weight: 500;
            color: #2c5a6e;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid #e2e8f0;
        }

        .nav-home {
            background: #1f5e7a;
            color: white;
            border: none;
            padding: 0.45rem 1.2rem;
            border-radius: 40px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .nav-home:hover {
            background: #124a62;
            transform: scale(0.96);
        }

        .settings-body {
            padding: 2rem 2rem 2rem 2rem;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .card-panel {
            background: #fbfdff;
            border-radius: 24px;
            border: 1px solid #e9f0f5;
            padding: 1.5rem 1.8rem;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e3b4a;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e2edf4;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            flex: 1;
            min-width: 140px;
        }

        .form-group label {
            display: block;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #547e94;
            margin-bottom: 6px;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 0.7rem 0.9rem;
            border: 1px solid #cfdfe9;
            border-radius: 16px;
            font-size: 0.9rem;
            background: white;
            font-family: inherit;
            transition: 0.2s;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #2c7da0;
            box-shadow: 0 0 0 3px rgba(44, 125, 160, 0.08);
        }

        .tax-item {
            background: #f9fafc;
            border-radius: 20px;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #eef2f8;
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
            align-items: center;
        }

        .tax-checkbox {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
        }
        
        .tax-checkbox input {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #1f5e7a;
        }

        .tax-item .form-group {
            flex: 1;
            min-width: 120px;
            margin-bottom: 0;
        }

        .tax-item .form-group input {
            background: #ffffff;
        }

        .remove-tax-btn {
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: #9aaebd;
            padding: 0.4rem 0.6rem;
            border-radius: 30px;
            transition: 0.2s;
        }

        .remove-tax-btn:hover {
            color: #d9534f;
            background: #fff0f0;
        }

        .add-tax-btn {
            background: white;
            border: 1px dashed #7f9eb0;
            padding: 0.5rem 1.2rem;
            border-radius: 40px;
            font-size: 0.8rem;
            font-weight: 500;
            color: #2c6c87;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 0.5rem;
            transition: 0.2s;
        }

        .add-tax-btn:hover {
            background: #eef5f9;
            border-color: #1f5e7a;
        }

        .logo-upload-area {
            margin-top: 1.8rem;
            background: #fefcf5;
            border: 1px dashed #cbdbe0;
            border-radius: 24px;
            padding: 1rem;
            text-align: center;
        }

        .file-label {
            background: #eef2f6;
            display: inline-block;
            padding: 0.5rem 1.2rem;
            border-radius: 60px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            color: #1f5e7a;
            margin-top: 6px;
            transition: 0.2s;
        }

        .file-label:hover {
            background: #e2eaf0;
        }

        .logo-preview {
            margin-top: 10px;
            max-width: 150px;
            max-height: 80px;
            display: block;
            margin-left: auto;
            margin-right: auto;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            object-fit: contain;
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
            border-top: 1px solid #ecf3f9;
            padding-top: 1.8rem;
        }

        .btn-save {
            background: #1f5e7a;
            color: white;
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-save:hover {
            background: #124a62;
            transform: scale(0.97);
        }

        .btn-secondary {
            background: white;
            border: 1px solid #bcd3e0;
            padding: 0.8rem 1.8rem;
            border-radius: 40px;
            font-weight: 500;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-secondary:hover {
            background: #f3f9ff;
            border-color: #2c7da0;
        }

        .alert-toast {
            position: fixed;
            bottom: 28px;
            left: 50%;
            transform: translateX(-50%) scale(0.95);
            background: #1e2f3a;
            color: white;
            padding: 0.9rem 1.8rem;
            border-radius: 60px;
            font-size: 0.85rem;
            font-weight: 500;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            z-index: 1000;
            backdrop-filter: blur(4px);
            transition: 0.2s;
            pointer-events: none;
        }

        .message {
            max-width: 1280px;
            margin: 0 auto 1rem auto;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-size: 0.9rem;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 800px) {
            .settings-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            body {
                padding: 1rem;
            }
            .settings-body {
                padding: 1.2rem;
            }
        }

        .inline-help {
            font-size: 0.7rem;
            color: #7f9eb0;
            margin-top: 4px;
        }
        
        .tax-header-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        a {
            text-decoration: none;
            color: inherit;
        }
    </style>
</head>
<body>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="settings-container">
    <div class="settings-header">
        <div class="logo-area">
            <h1>Account Settings</h1>
            <p>Manage business details, invoice rules & tax configuration</p>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
            <a href="invoice.php" style="text-decoration: none;">
                <div class="settings-badge"><span>📄</span> Invoice</div>
            </a>
        </div>
    </div>

    <form method="POST" action="" enctype="multipart/form-data" id="settingsForm">
        <div class="settings-body">
            <div class="settings-grid">
                <!-- LEFT COLUMN: Business Details -->
                <div class="card-panel">
                    <div class="card-title">
                        <span>🏢</span> Business Details
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Business Name</label>
                            <input type="text" name="business_name" id="businessName" placeholder="Your company name" value="<?php echo htmlspecialchars($existing_settings['business_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Business No.</label>
                            <input type="text" name="business_no" id="businessNo" placeholder="BN / Tax ID" value="<?php echo htmlspecialchars($existing_settings['business_no'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name</label>
                            <input type="text" name="first_name" id="firstName" placeholder="First name" value="<?php echo htmlspecialchars($existing_settings['first_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Last Name</label>
                            <input type="text" name="last_name" id="lastName" placeholder="Last name" value="<?php echo htmlspecialchars($existing_settings['last_name'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Telephone</label>
                            <input type="text" name="telephone" id="telephone" placeholder="Office phone" value="<?php echo htmlspecialchars($existing_settings['telephone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Mobile</label>
                            <input type="text" name="mobile" id="mobile" placeholder="Mobile" value="<?php echo htmlspecialchars($existing_settings['mobile'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Address 1</label>
                        <input type="text" name="address1" id="address1" placeholder="Street address" value="<?php echo htmlspecialchars($existing_settings['address1'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Address 2</label>
                        <input type="text" name="address2" id="address2" placeholder="Suite / Unit" value="<?php echo htmlspecialchars($existing_settings['address2'] ?? ''); ?>">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>City</label>
                            <input type="text" name="city" id="city" placeholder="City" value="<?php echo htmlspecialchars($existing_settings['city'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Province</label>
                            <input type="text" name="province" id="province" placeholder="Province" value="<?php echo htmlspecialchars($existing_settings['province'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Country</label>
                        <input type="text" name="country" id="country" placeholder="Country" value="<?php echo htmlspecialchars($existing_settings['country'] ?? ''); ?>">
                    </div>
                </div>

                <!-- RIGHT COLUMN: Invoice Settings + Taxes + Logo -->
                <div class="card-panel">
                    <div class="card-title">
                        <span>🧾</span> Invoice Settings
                    </div>
                    <div class="form-group">
                        <label>Next Invoice No.</label>
                        <input type="text" name="next_invoice_no" id="nextInvoiceNo" placeholder="e.g., INV-1001" value="<?php echo htmlspecialchars($existing_settings['next_invoice_no'] ?? 'INV-1001'); ?>">
                        <div class="inline-help">Will be used as starting point for next invoice</div>
                    </div>

                    <div class="tax-header-info" style="margin: 1.2rem 0 0.5rem 0;">
                        <label style="font-weight: 700; font-size: 0.8rem; color: #2c5a6e;">💰 Taxes configuration</label>
                        <span class="tax-badge" style="font-size:0.7rem;">✓ check to enable on invoice</span>
                    </div>
                    
                    <div id="taxesContainer">
                        <!-- Taxes will be loaded dynamically via JavaScript -->
                    </div>
                    <button type="button" class="add-tax-btn" id="addTaxBtn">+ Add New Tax</button>

                    <!-- Logo selection area -->
                    <div class="logo-upload-area">
                        <label style="font-weight: 600; font-size: 0.75rem; color: #2c5a6e;">Logo selection</label>
                        <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                            <img id="logoPreview" class="logo-preview" src="<?php echo !empty($logo_path) ? $logo_path : 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'80\' height=\'50\' viewBox=\'0 0 100 50\'%3E%3Crect width=\'100\' height=\'50\' fill=\'%23eef2f7\'/%3E%3Ctext x=\'22\' y=\'30\' fill=\'%23547e94\' font-size=\'10\'%3E Logo%3C/text%3E%3C/svg%3E'; ?>" alt="logo preview">
                            <label class="file-label" id="uploadLabel">
                                📁 Choose File
                                <input type="file" name="logo" id="logoUpload" accept="image/*" style="display: none;">
                            </label>
                            <input type="hidden" name="existing_logo" id="existingLogo" value="<?php echo htmlspecialchars($logo_path); ?>">
                            <div class="inline-help">Upload your company logo (PNG, JPG)</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="action-buttons">
                <button type="button" class="btn-secondary" id="exitBtn">Exit</button>
                <button type="submit" name="submit" class="btn-save">💾 Save Settings</button>
            </div>
        </div>
    </form>
</div>

<script>
    // Load existing taxes
    const existingTaxes = <?php echo json_encode($taxes); ?>;
    
    function createTaxRow(tax, index) {
        const taxId = tax.id || 'tax_' + Date.now() + '_' + index;
        const isEnabled = tax.enabled === true || tax.enabled === 'true' || tax.enabled === 1;
        const taxName = tax.name || 'New Tax';
        const taxValue = tax.value || 0;
        
        const taxDiv = document.createElement('div');
        taxDiv.className = 'tax-item';
        taxDiv.setAttribute('data-tax-id', taxId);
        
        taxDiv.innerHTML = `
            <div class="tax-checkbox">
                <input type="checkbox" class="tax-check" name="tax_enabled[]" value="1" ${isEnabled ? 'checked' : ''}>
            </div>
            <div class="form-group">
                <label>Tax Name</label>
                <input type="text" class="tax-name" name="tax_name[]" value="${escapeHtml(taxName)}" placeholder="Tax name">
            </div>
            <div class="form-group">
                <label>% Value</label>
                <input type="number" step="0.1" class="tax-value" name="tax_value[]" value="${taxValue}" placeholder="Rate">
            </div>
            <button type="button" class="remove-tax-btn">✕</button>
        `;
        
        const removeBtn = taxDiv.querySelector('.remove-tax-btn');
        removeBtn.addEventListener('click', function() {
            taxDiv.remove();
            showToast("🗑️ Tax row removed", "info");
        });
        
        return taxDiv;
    }
    
    function loadExistingTaxes() {
        const container = document.getElementById('taxesContainer');
        container.innerHTML = '';
        
        if (existingTaxes && existingTaxes.length > 0) {
            existingTaxes.forEach((tax, index) => {
                const taxRow = createTaxRow(tax, index);
                container.appendChild(taxRow);
            });
        } else {
            // Default taxes
            const defaultTaxes = [
                { id: 'tax_1', name: 'GST', value: 5, enabled: true },
                { id: 'tax_2', name: 'PST', value: 7, enabled: true }
            ];
            defaultTaxes.forEach((tax, index) => {
                const taxRow = createTaxRow(tax, index);
                container.appendChild(taxRow);
            });
        }
    }
    
    function escapeHtml(str) {
        if (!str) return "";
        return str.replace(/[&<>]/g, function(m) {
            if (m === "&") return "&amp;";
            if (m === "<") return "&lt;";
            if (m === ">") return "&gt;";
            return m;
        });
    }
    
    function showToast(message, type) {
        const toast = document.createElement('div');
        toast.className = 'alert-toast';
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
    
    function addNewTaxRow() {
        const container = document.getElementById('taxesContainer');
        const newTaxId = 'tax_' + Date.now();
        
        const taxDiv = document.createElement('div');
        taxDiv.className = 'tax-item';
        taxDiv.setAttribute('data-tax-id', newTaxId);
        
        taxDiv.innerHTML = `
            <div class="tax-checkbox">
                <input type="checkbox" class="tax-check" name="tax_enabled[]" value="1" checked>
            </div>
            <div class="form-group">
                <label>Tax Name</label>
                <input type="text" class="tax-name" name="tax_name[]" value="New Tax" placeholder="Tax name">
            </div>
            <div class="form-group">
                <label>% Value</label>
                <input type="number" step="0.1" class="tax-value" name="tax_value[]" value="0" placeholder="Rate">
            </div>
            <button type="button" class="remove-tax-btn">✕</button>
        `;
        
        const removeBtn = taxDiv.querySelector('.remove-tax-btn');
        removeBtn.addEventListener('click', function() {
            taxDiv.remove();
            showToast("🗑️ Tax row removed", "info");
        });
        
        container.appendChild(taxDiv);
        showToast("➕ New tax row added!", "success");
    }
    
    function initLogoUpload() {
        const fileInput = document.getElementById('logoUpload');
        const preview = document.getElementById('logoPreview');
        
        if (fileInput && preview) {
            fileInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(ev) {
                        preview.src = ev.target.result;
                        showToast("🖼️ Logo uploaded successfully!", "success");
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
        
        const label = document.getElementById('uploadLabel');
        if (label) {
            label.style.cursor = 'pointer';
        }
    }
    
    function handleExit() {
        const confirmExit = confirm("⚠️ Exit Settings?\n\nAny unsaved changes will be lost.\n\nPress OK to exit, Cancel to stay.");
        if (confirmExit) {
            window.location.href = "index.html";
        }
    }
    
    function handleHome() {
        const confirmHome = confirm("🏠 Go to Home?\n\nUnsaved changes will be lost.\n\nPress OK to go Home, Cancel to stay.");
        if (confirmHome) {
            window.location.href = "invoice.php";
        }
    }
    
    // Form submission handler
    const form = document.getElementById('settingsForm');
    form.addEventListener('submit', function(e) {
        const taxRows = document.querySelectorAll('.tax-item');
        const taxes = [];
        
        taxRows.forEach((row, index) => {
            const checkbox = row.querySelector('.tax-check');
            const nameInput = row.querySelector('.tax-name');
            const valueInput = row.querySelector('.tax-value');
            
            taxes.push({
                id: row.getAttribute('data-tax-id') || index,
                name: nameInput ? nameInput.value : '',
                value: valueInput ? parseFloat(valueInput.value) || 0 : 0,
                enabled: checkbox ? checkbox.checked : false
            });
        });
        
        let hiddenInput = document.querySelector('input[name="taxes_json"]');
        if (!hiddenInput) {
            hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'taxes_json';
            form.appendChild(hiddenInput);
        }
        hiddenInput.value = JSON.stringify(taxes);
    });
    
    document.addEventListener('DOMContentLoaded', () => {
        loadExistingTaxes();
        initLogoUpload();
        
        const addTaxBtn = document.getElementById('addTaxBtn');
        if (addTaxBtn) {
            addTaxBtn.addEventListener('click', addNewTaxRow);
        }
        
        const exitBtn = document.getElementById('exitBtn');
        if (exitBtn) {
            exitBtn.addEventListener('click', handleExit);
        }
        
        const homeBtn = document.getElementById('homeBtn');
        if (homeBtn) {
            homeBtn.addEventListener('click', handleHome);
        }
    });
</script>
</body>
</html>