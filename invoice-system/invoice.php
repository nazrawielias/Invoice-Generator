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
    // Create business_settings table if not exists
    $create_business_table = "
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
    if (!$conn->query($create_business_table)) {
        error_log("Error creating business_settings: " . $conn->error);
    }
    
    // Create invoices table if not exists
    $create_invoices_table = "
    CREATE TABLE IF NOT EXISTS invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_number VARCHAR(50) UNIQUE,
        client_name VARCHAR(255) NOT NULL,
        client_email VARCHAR(255),
        client_address TEXT,
        client_telephone VARCHAR(50),
        client_mobile VARCHAR(50),
        invoice_date DATE,
        items_data LONGTEXT,
        subtotal DECIMAL(10,2),
        tax_amount DECIMAL(10,2),
        tax_rate DECIMAL(10,2) DEFAULT 0,
        tax_breakdown LONGTEXT,
        total_amount DECIMAL(10,2),
        notes TEXT,
        balance_due DECIMAL(10,2),
        status VARCHAR(50) DEFAULT 'paid',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    if (!$conn->query($create_invoices_table)) {
        error_log("Error creating invoices: " . $conn->error);
    }
    
    // Get business settings for default values
    $business_settings = [];
    $taxes = [];
    $total_tax_rate = 0;
    
    $settings_query = "SELECT * FROM business_settings ORDER BY id DESC LIMIT 1";
    $settings_result = $conn->query($settings_query);
    if ($settings_result && $settings_result->num_rows > 0) {
        $business_settings = $settings_result->fetch_assoc();
        
        // Parse taxes from business settings
        if (isset($business_settings['taxes_data']) && !empty($business_settings['taxes_data'])) {
            $taxes = json_decode($business_settings['taxes_data'], true);
            if ($taxes && is_array($taxes)) {
                // Calculate total tax rate from enabled taxes
                foreach ($taxes as $tax) {
                    if (isset($tax['enabled']) && ($tax['enabled'] === true || $tax['enabled'] === 'true' || $tax['enabled'] === 1)) {
                        $total_tax_rate += floatval($tax['value']);
                    }
                }
            }
        }
    }
}

// Handle form submission
$message = '';
$message_type = '';
$print_content = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_invoice']) && $conn !== null) {
    // Validate required fields
    $client_name = trim($_POST['client_name'] ?? '');
    $balance_due = floatval($_POST['balance_due'] ?? 0);
    
    if (empty($client_name)) {
        $message = '❌ Client name is required!';
        $message_type = 'error';
    } elseif ($balance_due <= 0) {
        $message = '❌ Balance due must be greater than 0!';
        $message_type = 'error';
    } else {
        // Get form data
        $invoice_number = trim($_POST['invoice_number'] ?? '');
        $client_email = trim($_POST['client_email'] ?? '');
        $client_address = trim($_POST['client_address'] ?? '');
        $client_telephone = trim($_POST['client_telephone'] ?? '');
        $client_mobile = trim($_POST['client_mobile'] ?? '');
        $invoice_date = $_POST['invoice_date'] ?? date('Y-m-d');
        $items_json = $_POST['items_json'] ?? '[]';
        $subtotal = floatval($_POST['subtotal'] ?? 0);
        $tax_amount = floatval($_POST['tax_amount'] ?? 0);
        $total_amount = floatval($_POST['total_amount'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        $tax_rate = floatval($_POST['tax_rate'] ?? $total_tax_rate);
        $tax_breakdown = $_POST['tax_breakdown'] ?? json_encode($taxes);
        
        $sql = "INSERT INTO invoices (
            invoice_number, client_name, client_email, client_address, 
            client_telephone, client_mobile, invoice_date, items_data, 
            subtotal, tax_amount, tax_rate, tax_breakdown, total_amount, notes, balance_due
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param(
                "ssssssssdddsdds",
                $invoice_number,
                $client_name,
                $client_email,
                $client_address,
                $client_telephone,
                $client_mobile,
                $invoice_date,
                $items_json,
                $subtotal,
                $tax_amount,
                $tax_rate,
                $tax_breakdown,
                $total_amount,
                $notes,
                $balance_due
            );
            
            if ($stmt->execute()) {
                // Update next invoice number in business_settings
                preg_match('/(\d+)/', $invoice_number, $matches);
                if (!empty($matches)) {
                    $num_part = intval($matches[1]);
                    $new_num = $num_part + 1;
                    $new_invoice_number = preg_replace('/\d+/', $new_num, $invoice_number, 1);
                    
                    $update_sql = "UPDATE business_settings SET next_invoice_no = ? ORDER BY id DESC LIMIT 1";
                    $update_stmt = $conn->prepare($update_sql);
                    if ($update_stmt) {
                        $update_stmt->bind_param("s", $new_invoice_number);
                        $update_stmt->execute();
                        $update_stmt->close();
                    }
                }
                
                $message = '✅ Invoice saved successfully! Invoice Number: ' . $invoice_number;
                $message_type = 'success';
                
                // Prepare print content after successful save
                $print_content = [
                    'invoice_number' => $invoice_number,
                    'client_name' => $client_name,
                    'client_email' => $client_email,
                    'client_address' => $client_address,
                    'client_telephone' => $client_telephone,
                    'client_mobile' => $client_mobile,
                    'invoice_date' => $invoice_date,
                    'items_json' => $items_json,
                    'subtotal' => $subtotal,
                    'tax_amount' => $tax_amount,
                    'tax_rate' => $tax_rate,
                    'tax_breakdown' => $tax_breakdown,
                    'total_amount' => $total_amount,
                    'notes' => $notes,
                    'balance_due' => $balance_due
                ];
            } else {
                $message = '❌ Failed to save invoice: ' . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        } else {
            $message = '❌ Database error: ' . $conn->error;
            $message_type = 'error';
        }
    }
}

// Get next invoice number from business settings
$next_invoice_number = 'INV-1001';
if (!empty($business_settings) && isset($business_settings['next_invoice_no'])) {
    $next_invoice_number = $business_settings['next_invoice_no'];
}

if ($conn !== null) {
    $conn->close();
}

// Function to generate print HTML with tax list
function generatePrintHTML($data, $business_settings, $taxes) {
    function escapeHtmlPrint($str) {
        if (!$str) return "";
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }
    
    function formatMoneyPrint($value) {
        return number_format($value, 2, '.', ',');
    }
    
    $items = json_decode($data['items_json'], true);
    $itemsHTML = '';
    if ($items && is_array($items)) {
        foreach ($items as $item) {
            $itemName = $item['name'] ?? '';
            $description = $item['description'] ?? '';
            $qty = $item['quantity'] ?? 0;
            $price = $item['unit_price'] ?? 0;
            $subtotal = $item['subtotal'] ?? ($qty * $price);
            
            $itemsHTML .= '
                <tr style="border-bottom:1px solid #ddd;">
                    <td style="padding:8px; width:15%;">' . escapeHtmlPrint($itemName) . '</td>
                    <td style="padding:8px; width:35%;">' . escapeHtmlPrint($description) . '</td>
                    <td style="padding:8px; text-align:center; width:10%;">' . $qty . '</td>
                    <td style="padding:8px; text-align:right; width:15%;">$' . formatMoneyPrint($price) . '</td>
                    <td style="padding:8px; text-align:right; width:15%; font-weight:500;">$' . formatMoneyPrint($subtotal) . '</td>
                </table>
            ';
        }
    }
    
    // Business details
    $businessName = $business_settings['business_name'] ?? '';
    $businessNo = $business_settings['business_no'] ?? '';
    $firstName = $business_settings['first_name'] ?? '';
    $lastName = $business_settings['last_name'] ?? '';
    $businessTel = $business_settings['telephone'] ?? '';
    $businessMobile = $business_settings['mobile'] ?? '';
    $address1 = $business_settings['address1'] ?? '';
    $address2 = $business_settings['address2'] ?? '';
    $city = $business_settings['city'] ?? '';
    $province = $business_settings['province'] ?? '';
    $country = $business_settings['country'] ?? '';
    $logo_path = $business_settings['logo_path'] ?? '';
    
    $fullAddress = $address1;
    if ($address2) $fullAddress .= ", $address2";
    if ($city) $fullAddress .= ", $city";
    if ($province) $fullAddress .= ", $province";
    if ($country) $fullAddress .= ", $country";
    
    $ownerLine = "";
    if ($firstName || $lastName) {
        $ownerLine = '<div style="font-size:0.85rem; margin-top: 4px;">' . escapeHtmlPrint($firstName) . ' ' . escapeHtmlPrint($lastName) . '</div>';
    }
    
    $contactLine = "";
    if ($businessTel || $businessMobile) {
        $telPart = $businessTel ? "Tel: " . escapeHtmlPrint($businessTel) : "";
        $mobilePart = $businessMobile ? "Mobile: " . escapeHtmlPrint($businessMobile) : "";
        $contactLine = '<div style="font-size:0.8rem; color:#2c5e6e;">' . $telPart . ($telPart && $mobilePart ? " · " : "") . $mobilePart . '</div>';
    }
    
    $logoHTML = '';
    if (!empty($logo_path) && file_exists($logo_path)) {
        $logoHTML = '<div style="margin-bottom: 10px; text-align: left;">
                        <img src="' . $logo_path . '" alt="Company Logo" style="max-width: 150px; max-height: 80px; object-fit: contain;">
                     </div>';
    }
    
    $businessHTML = '
        <div style="line-height:1.4;">
            ' . $logoHTML . '
            ' . ($businessName ? '<div style="font-weight:800; font-size:1.25rem; color:#1f4b5e;">' . escapeHtmlPrint($businessName) . '</div>' : '') . '
            ' . ($businessNo ? '<div style="font-size:0.75rem; color:#4e6f7e;">BN: ' . escapeHtmlPrint($businessNo) . '</div>' : '') . '
            ' . $ownerLine . '
            ' . ($fullAddress ? '<div style="font-size:0.85rem; margin-top: 5px;">' . escapeHtmlPrint($fullAddress) . '</div>' : '') . '
            ' . $contactLine . '
        </div>
    ';
    
    $itemsTotal = $data['subtotal'] ?? 0;
    $balanceDue = $data['balance_due'] ?? 0;
    
    // Build individual tax breakdown HTML for print
    $taxBreakdownHTML = '';
    $totalTaxAmount = 0;
    
    if ($taxes && is_array($taxes)) {
        foreach ($taxes as $tax) {
            if (isset($tax['enabled']) && ($tax['enabled'] === true || $tax['enabled'] === 'true' || $tax['enabled'] === 1)) {
                $taxRate = floatval($tax['value']);
                $taxAmount = $itemsTotal * ($taxRate / 100);
                $totalTaxAmount += $taxAmount;
                $taxBreakdownHTML .= '
                    <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.25rem;">
                        <span>' . escapeHtmlPrint($tax['name']) . ' (' . $taxRate . '%)</span>
                        <span>$' . formatMoneyPrint($taxAmount) . '</span>
                    </div>
                ';
            }
        }
    }
    
    $taxSectionHTML = '';
    if (!empty($taxBreakdownHTML)) {
        $taxSectionHTML = '
            <div style="border-top: 1px dashed #dde6ef; padding-top: 0.5rem; margin-top: 0.25rem;">
                ' . $taxBreakdownHTML . '
            </div>
        ';
    } else {
        $taxSectionHTML = '<div style="display: flex; justify-content: space-between; margin: 0.5rem 0;"><span>No taxes</span><span>$0.00</span></div>';
    }
    
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <title>Invoice ' . escapeHtmlPrint($data['invoice_number']) . '</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; margin: 2rem; background: white; color: #1a2a36; }
            .print-card { max-width: 1100px; margin: 0 auto; padding: 1.8rem; border: 1px solid #dce7ef; border-radius: 28px; background: #fff; }
            .upper-row { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1.2rem; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 2px solid #edf2f7; }
            .business-detail-col { max-width: 400px; background: #fbfefe; padding: 0.8rem 1rem; border-radius: 20px; border: 1px solid #e9f0f5; }
            .invoice-title-col { text-align: right; }
            .invoice-title-col h2 { font-size: 1.8rem; font-weight: 700; background: linear-gradient(135deg,#1f3b4c,#2c5a6e); -webkit-background-clip: text; background-clip: text; color: transparent; }
            .invoice-id-badge { font-weight: 600; background: #f2f6fa; padding: 0.3rem 0.9rem; border-radius: 40px; font-size: 0.9rem; margin-top: 0.5rem; }
            .client-block { background: #fafdff; border-radius: 20px; padding: 1rem 1.3rem; margin: 1.2rem 0; border: 1px solid #eef2f8; }
            table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
            th { background: #f1f5f9; padding: 10px 8px; text-align: left; font-weight: 600; border-bottom: 1px solid #cfdfe9; }
            td { padding: 10px 8px; border-bottom: 1px solid #ecf3f8; }
            .totals-print { width: 320px; margin-left: auto; margin-top: 20px; text-align: right; padding: 12px 16px; background: #fbfefe; border-radius: 20px; border: 1px solid #e9f0f5; }
            .totals-print .balance-total { font-size: 1.35rem; font-weight: 800; border-top: 2px solid #cbdde9; margin-top: 8px; padding-top: 8px; color:#144d63; }
            .note-print { margin-top: 24px; padding-top: 12px; border-top: 1px solid #e2edf4; font-size: 0.85rem; }
            .footer { text-align: center; font-size: 0.7rem; margin-top: 1.5rem; color: #8da3b2; }
            @media print {
                body { margin: 0; padding: 0.8rem; }
                .print-card { box-shadow: none; border: 1px solid #ccc; }
            }
        </style>
    </head>
    <body>
        <div class="print-card">
            <div class="upper-row">
                <div class="business-detail-col">' . $businessHTML . '</div>
                <div class="invoice-title-col">
                    <h2>INVOICE</h2>
                    <div class="invoice-id-badge"> Invoice Number: ' . escapeHtmlPrint($data['invoice_number']) . '</div>
                    <div style="font-size:0.85rem; margin-top: 6px;">Date: ' . date('Y-m-d', strtotime($data['invoice_date'])) . '</div>
                </div>
            </div>
            
            <div class="client-block">
                <strong>Bill To:</strong><br>
                ' . escapeHtmlPrint($data['client_name']) . '<br>
                ' . escapeHtmlPrint($data['client_email']) . '<br>
                ' . escapeHtmlPrint($data['client_address']) . '<br>
                Tel: ' . escapeHtmlPrint($data['client_telephone']) . ' &nbsp; Mobile: ' . escapeHtmlPrint($data['client_mobile']) . '
            </div>
            
            <table>
                <thead>
                    <tr><th>Item</th><th>Description</th><th>Qty</th><th>Unit price</th><th>Subtotal</th></tr>
                </thead>
                <tbody>' . ($itemsHTML ?: '<tr><td colspan="5" style="text-align:center;">— No line items —</td></tr>') . '</tbody>
            </table>
            
            <div class="totals-print">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <strong>Items Total:</strong>
                    <span>$' . formatMoneyPrint($itemsTotal) . '</span>
                </div>
                ' . $taxSectionHTML . '
                <div class="balance-total" style="display: flex; justify-content: space-between;">
                    <span>Balance due:</span>
                    <span>$' . formatMoneyPrint($balanceDue) . '</span>
                </div>
            </div>
            
            <div class="note-print">
                <strong>📌 Note / Terms:</strong><br>' . nl2br(escapeHtmlPrint($data['notes'])) . '
            </div>
        </div>
    </body>
    </html>
    ';
}

// If print content is set, trigger print and then show message
if ($print_content !== null) {
    $printHTML = generatePrintHTML($print_content, $business_settings, $taxes);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Print Invoice</title>
        <script>
            function printAndRedirect() {
                window.print();
                setTimeout(function() {
                    window.location.href = 'invoice.php?msg=success&invoice=<?php echo urlencode($print_content['invoice_number']); ?>';
                }, 1000);
            }
        </script>
    </head>
    <body onload="printAndRedirect()">
        <?php echo $printHTML; ?>
    </body>
    </html>
    <?php
    exit();
}

// Check for success message from print redirect
if (isset($_GET['msg']) && $_GET['msg'] == 'success') {
    $message = '✅ Invoice saved and printed successfully! Invoice Number: ' . htmlspecialchars($_GET['invoice'] ?? '');
    $message_type = 'success';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <title>Invoice Flow | Business Details on Print</title>
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

        .invoice-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 28px;
            box-shadow: 0 20px 35px -12px rgba(0, 0, 0, 0.12);
            overflow: hidden;
        }

        .invoice-header {
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
            cursor: pointer;
            transition: 0.2s;
        }
        
        .settings-badge:hover {
            background: #eef2f6;
        }

        .invoice-body {
            padding: 1.8rem 2rem 2rem 2rem;
        }

        .business-panel {
            background: #f9fbfe;
            border-radius: 20px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.8rem;
            border: 1px solid #e6edf3;
        }
        
        .business-panel .section-title {
            margin-bottom: 0.8rem;
        }
        
        .business-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .business-grid .input-group {
            flex: 1 1 180px;
            margin-bottom: 0.5rem;
        }
        
        .inline-group {
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
        }
        
        .inline-group .input-group {
            flex: 1;
        }

        .details-row {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
            margin-bottom: 2rem;
            background: #fbfdff;
            border-radius: 20px;
            padding: 1rem 0;
        }

        .client-section {
            flex: 2;
            min-width: 240px;
        }

        .invoice-meta {
            flex: 1.2;
            min-width: 220px;
            background: #f9fbfd;
            padding: 0.9rem 1.2rem;
            border-radius: 20px;
            border: 1px solid #eef2f8;
        }

        .section-title {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-weight: 600;
            color: #4b6a7c;
            margin-bottom: 1rem;
        }

        .input-group {
            margin-bottom: 1rem;
        }

        .input-group label {
            display: block;
            font-size: 0.75rem;
            font-weight: 500;
            color: #3f5a6b;
            margin-bottom: 0.25rem;
        }

        .input-group input,
        .input-group select {
            width: 100%;
            padding: 0.65rem 0.9rem;
            border: 1px solid #cfdfe9;
            border-radius: 14px;
            font-size: 0.9rem;
            background: white;
            font-family: inherit;
        }

        .inline-row {
            display: flex;
            gap: 0.8rem;
        }

        .inline-row .input-group {
            flex: 1;
        }

        .meta-field {
            margin-bottom: 1rem;
        }

        .meta-field label {
            font-size: 0.7rem;
            font-weight: 500;
            color: #547183;
            display: block;
            margin-bottom: 4px;
        }

        .meta-field input {
            background: white;
            border: 1px solid #e2edf4;
            border-radius: 14px;
            padding: 0.6rem 0.8rem;
            width: 100%;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .items-table-wrapper {
            overflow-x: auto;
            margin: 1.5rem 0 1rem;
            border-radius: 20px;
            border: 1px solid #edf2f7;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            min-width: 700px;
        }

        .invoice-table th:nth-child(1),
        .invoice-table td:nth-child(1) { width: 14%; min-width: 110px; }
        .invoice-table th:nth-child(2),
        .invoice-table td:nth-child(2) { width: 34%; min-width: 200px; }
        .invoice-table th:nth-child(3),
        .invoice-table td:nth-child(3) { width: 10%; min-width: 85px; }
        .invoice-table th:nth-child(4),
        .invoice-table td:nth-child(4) { width: 14%; min-width: 110px; }
        .invoice-table th:nth-child(5),
        .invoice-table td:nth-child(5) { width: 14%; min-width: 100px; }
        .invoice-table th:nth-child(6),
        .invoice-table td:nth-child(6) { width: 8%; min-width: 60px; text-align: center; }

        .invoice-table th {
            background: #f2f6fa;
            padding: 0.9rem 0.8rem;
            text-align: left;
            font-weight: 600;
            color: #1e3b4a;
            border-bottom: 1px solid #e2edf4;
        }

        .invoice-table td {
            padding: 0.8rem;
            border-bottom: 1px solid #ecf3f8;
            vertical-align: middle;
        }

        .invoice-table input {
            width: 100%;
            padding: 0.55rem 0.6rem;
            border: 1px solid #dce7ef;
            border-radius: 12px;
            font-size: 0.85rem;
            font-family: inherit;
            background: white;
            transition: 0.2s;
        }

        .invoice-table input:focus {
            outline: none;
            border-color: #2c7da0;
            box-shadow: 0 0 0 2px rgba(44, 125, 160, 0.1);
        }

        .qty-input, .price-input { text-align: right; }
        .subtotal-cell { font-weight: 600; color: #1a4b60; background: #fefefe; }

        .cancel-btn {
            background: none;
            border: none;
            font-size: 1.3rem;
            cursor: pointer;
            color: #9aaebd;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 4px 8px;
            border-radius: 30px;
            font-weight: bold;
        }

        .cancel-btn:hover {
            color: #d9534f;
            background: #fff0f0;
        }

        .add-line-btn {
            margin: 0.8rem 0 1.8rem;
            background: white;
            border: 1px dashed #2c7da0;
            background: #fbfefe;
            padding: 0.65rem 1.5rem;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.85rem;
            color: #2c6c87;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .add-line-btn:hover {
            background: #eef5f9;
            border-color: #1f5e7a;
            color: #1f5e7a;
            transform: translateY(-1px);
        }

        .totals-panel {
            display: flex;
            justify-content: flex-end;
            margin-top: 1rem;
            margin-bottom: 2rem;
        }

        .totals-card {
            width: 340px;
            background: #fafdff;
            border-radius: 24px;
            padding: 1.2rem 1.5rem;
            border: 1px solid #e9f0f5;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
        }

        .total-row.tax-row {
            color: #436f82;
            font-size: 0.85rem;
            padding: 0.25rem 0;
        }

        .total-row.balance {
            font-weight: 800;
            font-size: 1.2rem;
            border-top: 2px solid #cbdde9;
            margin-top: 0.5rem;
            padding-top: 0.8rem;
            color: #144d63;
        }

        .note-section {
            margin-top: 1.2rem;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: flex-end;
            gap: 1rem;
            border-top: 1px solid #ecf3f9;
            padding-top: 1.5rem;
        }

        .note-field {
            flex: 2;
        }

        .note-field label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #547e94;
            display: block;
            margin-bottom: 6px;
        }

        .note-field textarea {
            width: 100%;
            border: 1px solid #dce7ef;
            border-radius: 18px;
            padding: 0.7rem 1rem;
            font-family: inherit;
            font-size: 0.85rem;
            resize: vertical;
            background: white;
        }

        .save-print-group {
            display: flex;
            gap: 0.8rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn-primary {
            background: #1f5e7a;
            color: white;
            border: none;
            padding: 0.7rem 1.5rem;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-primary:hover {
            background: #124a62;
            transform: scale(0.97);
        }

        .message {
            max-width: 1200px;
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

        @media (max-width: 720px) {
            body { padding: 1rem; }
            .invoice-body { padding: 1.2rem; }
            .totals-panel { justify-content: stretch; }
            .totals-card { width: 100%; }
            .business-grid .input-group { flex: 1 1 100%; }
        }
        
        a {
            text-decoration: none;
            color: inherit;
        }
        
        .tax-info {
            background: #eef3fc;
            padding: 0.5rem 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            color: #1f5e7a;
        }
        
        #taxBreakdownList {
            margin: 0.5rem 0;
        }
    </style>
</head>
<body>

<?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div class="message error">
        <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<div class="invoice-container">
    <div class="invoice-header">
        <div class="logo-area">
            <h1>Smart Invoice</h1>
            <p>Dynamic rows · business details on print</p>
        </div>
        <div style="display: flex; align-items: center; gap: 8px">
            <a href="settings.php" style="text-decoration: none;">
                <div class="settings-badge"><span>⚙️</span> Settings</div>
            </a>
        </div>
    </div>

    <form method="POST" action="" id="invoiceForm">
        <div class="invoice-body">
            <!-- BUSINESS DETAILS SECTION -->
            <div class="business-panel">
                <div class="section-title">🏢 Your Business Info</div>
                <div class="business-grid">
                    <div class="input-group">
                        <label>Business Name</label>
                        <input type="text" id="businessName" placeholder="Company Name" value="<?php echo htmlspecialchars($business_settings['business_name'] ?? ''); ?>" disabled>
                    </div>
                    <div class="input-group">
                        <label>Business No.</label>
                        <input type="text" id="businessNo" placeholder="BN 12345 6789" value="<?php echo htmlspecialchars($business_settings['business_no'] ?? ''); ?>" disabled>
                    </div>
                </div>
                <div class="inline-group">
                    <div class="input-group">
                        <label>First Name</label>
                        <input type="text" id="firstName" placeholder="First" value="<?php echo htmlspecialchars($business_settings['first_name'] ?? ''); ?>" disabled>
                    </div>
                    <div class="input-group">
                        <label>Last Name</label>
                        <input type="text" id="lastName" placeholder="Last" value="<?php echo htmlspecialchars($business_settings['last_name'] ?? ''); ?>" disabled>
                    </div>
                    <div class="input-group">
                        <label>Telephone</label>
                        <input type="text" id="businessTel" placeholder="Office" value="<?php echo htmlspecialchars($business_settings['telephone'] ?? ''); ?>" disabled>
                    </div>
                    <div class="input-group">
                        <label>Mobile (optional)</label>
                        <input type="text" id="businessMobile" placeholder="Mobile" value="<?php echo htmlspecialchars($business_settings['mobile'] ?? ''); ?>" disabled>
                    </div>
                </div>
                <div class="inline-group">
                    <div class="input-group">
                        <label>Address 1</label>
                        <input type="text" id="address1" placeholder="Street address" value="<?php echo htmlspecialchars($business_settings['address1'] ?? ''); ?>" disabled>
                    </div>
                    <div class="input-group">
                        <label>Address 2 (optional)</label>
                        <input type="text" id="address2" placeholder="Suite / Floor" value="<?php echo htmlspecialchars($business_settings['address2'] ?? ''); ?>" disabled>
                    </div>
                </div>
                <div class="inline-group">
                    <div class="input-group">
                        <label>City</label>
                        <input type="text" id="city" placeholder="City" value="<?php echo htmlspecialchars($business_settings['city'] ?? ''); ?>" disabled>
                    </div>
                    <div class="input-group">
                        <label>Province</label>
                        <input type="text" id="province" placeholder="Province" value="<?php echo htmlspecialchars($business_settings['province'] ?? ''); ?>" disabled>
                    </div>
                    <div class="input-group">
                        <label>Country</label>
                        <input type="text" id="country" placeholder="Country" value="<?php echo htmlspecialchars($business_settings['country'] ?? ''); ?>" disabled>
                    </div>
                </div>
            </div>

            <!-- Client & meta row -->
            <div class="details-row">
                <div class="client-section">
                    <div class="section-title">Client Details</div>
                    <div class="input-group">
                        <label>Full Name* <span style="color:red;">(required)</span></label>
                        <input type="text" id="fullName" name="client_name" placeholder="Alex Johnson" required>
                    </div>
                    <div class="input-group">
                        <label>Email</label>
                        <input type="email" id="email" name="client_email" placeholder="client@example.com">
                    </div>
                    <div class="input-group">
                        <label>Address</label>
                        <input type="text" id="address" name="client_address" placeholder="451 Maple Avenue, Toronto, ON">
                    </div>
                    <div class="inline-row">
                        <div class="input-group">
                            <label>Telephone</label>
                            <input type="text" id="telephone" name="client_telephone" placeholder="(555) 123-4567">
                        </div>
                        <div class="input-group">
                            <label>Mobile</label>
                            <input type="text" id="mobile" name="client_mobile" placeholder="(555) 987-6543">
                        </div>
                    </div>
                </div>
                <div class="invoice-meta">
                    <div class="section-title">Invoice Reference</div>
                    <div class="meta-field">
                        <label>Invoice No:</label>
                        <input type="text" id="invoiceNo" name="invoice_number" value="<?php echo htmlspecialchars($next_invoice_number); ?>" readonly style="background:#f5f7fa;">
                    </div>
                    <div class="meta-field">
                        <label>Invoice Date:</label>
                        <input type="date" id="invoiceDate" name="invoice_date" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
            </div>
            
            <!-- Tax Information Display -->
            <div class="tax-info">
                <strong>💰 Taxes Applied:</strong> <?php 
                    $tax_names = [];
                    if ($taxes && is_array($taxes)) {
                        foreach ($taxes as $tax) {
                            if (isset($tax['enabled']) && ($tax['enabled'] === true || $tax['enabled'] === 'true' || $tax['enabled'] === 1)) {
                                $tax_names[] = $tax['name'] . ' (' . $tax['value'] . '%)';
                            }
                        }
                    }
                    if (empty($tax_names)) {
                        echo 'No taxes configured';
                    } else {
                        echo implode(' + ', $tax_names);
                    }
                ?>
            </div>

            <!-- Items table -->
            <div class="items-table-wrapper">
                <table class="invoice-table" id="invoiceItemsTable">
                    <thead>
                        <tr><th>Item</th><th>Description</th><th style="width:85px">Qty</th><th style="width:110px">Unit price</th><th style="width:110px">Subtotal</th><th style="width:50px">Cancel</th></tr>
                    </thead>
                    <tbody id="tableBody"></tbody>
                </table>
            </div>

            <button type="button" class="add-line-btn" id="addRowBtn">+ Add new line</button>

            <!-- Totals section with tax list -->
            <div class="totals-panel">
                <div class="totals-card">
                    <div class="total-row">
                        <span>Items Total</span>
                        <span id="itemsTotalDisplay">$0.00</span>
                    </div>
                    <div id="taxBreakdownList" style="margin: 0.5rem 0;">
                        <!-- Individual taxes will be listed here -->
                    </div>
                    <div class="total-row balance">
                        <span>Balance due:</span>
                        <span id="balanceDueDisplay">$0.00</span>
                    </div>
                </div>
            </div>

            <div class="note-section">
                <div class="note-field">
                    <label>📝 Note / Terms</label>
                    <textarea id="invoiceNote" name="notes" rows="2" placeholder="Payment terms, thanks, etc.">Thank you for your business!</textarea>
                </div>
                <div class="save-print-group">
                    <button type="submit" name="save_invoice" class="btn-primary">💾 Save & Print</button>
                </div>
            </div>
            <div style="width:100%; display: flex; align-items: center; justify-content: center; gap: 0.5rem; flex-wrap: wrap; margin-top: 1.5rem;">
                <span style="font-size: 0.85rem; color: #9ca3af;">© 2026</span>
                <a href="https://nazrawiportofolio.vercel.app/" style="font-size: 0.85rem; color: #1f5e7a; text-decoration: none; font-weight: 500;">
                    Developed By Nazrawi Elias
                </a>
            </div>
        </div>
        
        <!-- Hidden fields for form submission -->
        <input type="hidden" name="items_json" id="itemsJson">
        <input type="hidden" name="subtotal" id="subtotalHidden">
        <input type="hidden" name="tax_amount" id="taxAmountHidden">
        <input type="hidden" name="tax_rate" id="taxRateHidden" value="<?php echo $total_tax_rate; ?>">
        <input type="hidden" name="tax_breakdown" id="taxBreakdownHidden" value='<?php echo json_encode($taxes); ?>'>
        <input type="hidden" name="total_amount" id="totalAmountHidden">
        <input type="hidden" name="balance_due" id="balanceDueHidden">
    </form>
</div>

<script>
    // Tax rate and data from PHP
    const TAX_RATE = <?php echo $total_tax_rate; ?> / 100;
    const TAXES_DATA = <?php echo json_encode($taxes); ?>;
    
    function formatMoney(value) {
        return new Intl.NumberFormat("en-CA", {
            style: "currency",
            currency: "CAD",
            minimumFractionDigits: 2,
        }).format(value);
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
    
    function getTaxBreakdown() {
        if (!TAXES_DATA || !Array.isArray(TAXES_DATA)) return [];
        const enabledTaxes = TAXES_DATA.filter(tax => tax.enabled === true || tax.enabled === 'true' || tax.enabled === 1);
        return enabledTaxes;
    }

    function refreshTotals() {
        const tbody = document.getElementById("tableBody");
        const rows = tbody.querySelectorAll(".item-row");
        let totalItemsSum = 0;

        for (let row of rows) {
            const qtyInput = row.querySelector(".qty-input");
            const priceInput = row.querySelector(".price-input");
            const subtotalSpan = row.querySelector(".subtotal-value");

            let qty = parseFloat(qtyInput ? qtyInput.value : 0);
            let price = parseFloat(priceInput ? priceInput.value : 0);
            if (isNaN(qty)) qty = 0;
            if (isNaN(price)) price = 0;

            const subtotal = qty * price;
            if (subtotalSpan) subtotalSpan.innerText = formatMoney(subtotal);
            totalItemsSum += subtotal;
        }

        // Get enabled taxes
        const enabledTaxes = getTaxBreakdown();
        const taxBreakdownHTML = [];
        let totalTaxAmount = 0;
        
        // Calculate each tax individually
        for (let tax of enabledTaxes) {
            const taxAmount = totalItemsSum * (tax.value / 100);
            totalTaxAmount += taxAmount;
            taxBreakdownHTML.push(`
                <div class="total-row tax-row" style="display: flex; justify-content: space-between; font-size: 0.85rem; padding: 0.25rem 0;">
                    <span>${escapeHtml(tax.name)} (${tax.value}%)</span>
                    <span>${formatMoney(taxAmount)}</span>
                </div>
            `);
        }
        
        const balanceDue = totalItemsSum + totalTaxAmount;
        
        // Update displays
        document.getElementById("itemsTotalDisplay").innerText = formatMoney(totalItemsSum);
        document.getElementById("balanceDueDisplay").innerText = formatMoney(balanceDue);
        
        // Update tax breakdown list
        const taxBreakdownContainer = document.getElementById("taxBreakdownList");
        if (taxBreakdownContainer) {
            if (taxBreakdownHTML.length > 0) {
                taxBreakdownContainer.innerHTML = taxBreakdownHTML.join('');
            } else {
                taxBreakdownContainer.innerHTML = '<div class="total-row tax-row" style="display: flex; justify-content: space-between;"><span>No taxes</span><span>$0.00</span></div>';
            }
        }
        
        // Update hidden fields
        document.getElementById("subtotalHidden").value = totalItemsSum.toFixed(2);
        document.getElementById("taxAmountHidden").value = totalTaxAmount.toFixed(2);
        document.getElementById("totalAmountHidden").value = (totalItemsSum + totalTaxAmount).toFixed(2);
        document.getElementById("balanceDueHidden").value = balanceDue.toFixed(2);
    }

    function createItemRow(itemName = "", description = "", qty = 1, price = 0) {
        const tr = document.createElement("tr");
        tr.className = "item-row";

        const tdItem = document.createElement("td");
        const itemInput = document.createElement("input");
        itemInput.type = "text";
        itemInput.placeholder = "Service / Product";
        itemInput.value = itemName;
        itemInput.classList.add("item-name-input");
        tdItem.appendChild(itemInput);

        const tdDesc = document.createElement("td");
        const descInput = document.createElement("input");
        descInput.type = "text";
        descInput.placeholder = "Detailed description";
        descInput.value = description;
        descInput.classList.add("desc-input");
        tdDesc.appendChild(descInput);

        const tdQty = document.createElement("td");
        const qtyInput = document.createElement("input");
        qtyInput.type = "number";
        qtyInput.step = "1";
        qtyInput.min = "0";
        qtyInput.value = qty;
        qtyInput.classList.add("qty-input");
        tdQty.appendChild(qtyInput);

        const tdPrice = document.createElement("td");
        const priceInput = document.createElement("input");
        priceInput.type = "number";
        priceInput.step = "0.01";
        priceInput.min = "0";
        priceInput.value = price;
        priceInput.classList.add("price-input");
        tdPrice.appendChild(priceInput);

        const tdSubtotal = document.createElement("td");
        const subtotalSpan = document.createElement("span");
        subtotalSpan.className = "subtotal-value";
        subtotalSpan.innerText = formatMoney(qty * price);
        tdSubtotal.appendChild(subtotalSpan);
        tdSubtotal.classList.add("subtotal-cell");

        const tdCancel = document.createElement("td");
        const cancelBtn = document.createElement("button");
        cancelBtn.innerHTML = "✕";
        cancelBtn.className = "cancel-btn";
        cancelBtn.title = "Remove row";
        cancelBtn.addEventListener("click", function() {
            tr.remove();
            refreshTotals();
        });
        tdCancel.appendChild(cancelBtn);
        tdCancel.style.textAlign = "center";

        tr.appendChild(tdItem);
        tr.appendChild(tdDesc);
        tr.appendChild(tdQty);
        tr.appendChild(tdPrice);
        tr.appendChild(tdSubtotal);
        tr.appendChild(tdCancel);

        qtyInput.addEventListener("input", () => refreshTotals());
        priceInput.addEventListener("input", () => refreshTotals());

        return tr;
    }

    function addNewLine() {
        const tbody = document.getElementById("tableBody");
        const newRow = createItemRow("", "", 0, 0);
        tbody.appendChild(newRow);
        refreshTotals();
    }

    function initializeRows() {
        const tbody = document.getElementById("tableBody");
        tbody.innerHTML = "";
        const row1 = createItemRow("", "", 0, 0);
        const row2 = createItemRow("", "", 0, 0);
        const row3 = createItemRow("", "", 0, 0);
        tbody.appendChild(row1);
        tbody.appendChild(row2);
        tbody.appendChild(row3);
        refreshTotals();
    }

    document.addEventListener("DOMContentLoaded", () => {
        initializeRows();
        const addBtn = document.getElementById("addRowBtn");
        if (addBtn) addBtn.addEventListener("click", addNewLine);
        
        // Update items JSON before form submission
        const form = document.getElementById("invoiceForm");
        form.addEventListener("submit", function(e) {
            const tbody = document.getElementById("tableBody");
            const rows = tbody.querySelectorAll(".item-row");
            const items = [];
            
            for (let row of rows) {
                const itemName = row.querySelector(".item-name-input")?.value || "";
                const description = row.querySelector(".desc-input")?.value || "";
                const qty = parseFloat(row.querySelector(".qty-input")?.value || 0);
                const price = parseFloat(row.querySelector(".price-input")?.value || 0);
                
                items.push({
                    name: itemName,
                    description: description,
                    quantity: qty,
                    unit_price: price,
                    subtotal: qty * price
                });
            }
            
            document.getElementById("itemsJson").value = JSON.stringify(items);
        });
    });
</script>
</body>
</html>