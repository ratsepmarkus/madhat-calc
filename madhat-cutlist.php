<?php
/**
 * Plugin Name: Madhat Cutlist Calculator (Pro)
 * Description: Kalkulaator hinnavahimiku, materjali valiku ja CSV ekspordiga.
 * Version: 1.3
 * Author: Madhat
 * Update URI: https://github.com/ratsepmarkus/madhat-calculator
 */

if (!defined('ABSPATH')) {
    exit; 
}

// ---------------------------------------------------------
// 0. AUTOMATIC UPDATER (GitHub)
// ---------------------------------------------------------
// Veendu, et kaust 'plugin-update-checker' on plugina kaustas olemas!
if (file_exists(__DIR__ . '/plugin-update-checker/plugin-update-checker.php')) {
    require 'plugin-update-checker/plugin-update-checker.php';
    
    // Kasutame v5 nimeruumi (toimib 5.0 - 5.x versioonidega)
    use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

    $myUpdateChecker = PucFactory::buildUpdateChecker(
        'https://github.com/ratsepmarkus/madhat-calculator', // Sinu Repo URL
        __FILE__, // See fail
        'madhat-cutlist-calculator-pro' // Unikaalne slug
    );

    // Valikuline: Määra haru (branch), kui ei kasuta masterit/maini
    $myUpdateChecker->setBranch('main');

    // See rida võimaldab tõmmata GitHubi Release alt .zip faili
    // (See on stabiilsem kui otse koodi tõmbamine)
    $myUpdateChecker->getVcsApi()->enableReleaseAssets();
}

// ---------------------------------------------------------
// 1. VORMI KUVAMINE
// ---------------------------------------------------------
function madhat_render_form() {
    ob_start();
    ?>
    <style>
        .madhat-wrapper { 
            max_width: 600px;
            margin: 0 auto; 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            padding: 20px; 
            background: #ffffff; 
            border: 1px solid #e1e4e8; 
            border-radius: 6px; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .madhat-header { text-align: center; margin-bottom: 20px; }
        .madhat-header h3 { margin: 0 0 5px 0; font-size: 1.2em; }
        
        .madhat-row { display: flex; gap: 10px; margin-bottom: 8px; align-items: flex-end; }
        .madhat-col { flex: 1; min-width: 0; }
        
        .madhat-label { display: block; font-weight: 600; margin-bottom: 3px; font-size: 0.85em; color: #444; }
        .madhat-input, .madhat-select, .madhat-textarea { 
            width: 100%; padding: 6px 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; box-sizing: border-box;
        }
        
        .madhat-btn { 
            background-color: #333; color: #fff; padding: 8px 16px; border: none; 
            cursor: pointer; border-radius: 4px; font-size: 14px; font-weight: 500; transition: background 0.2s;
        }
        .madhat-btn:hover { background-color: #555; }
        .btn-sm { padding: 4px 10px; font-size: 12px; }
        .btn-add { background-color: #f0f0f0; color: #333; border: 1px solid #ccc; width: 100%; margin-top: 5px; }
        .btn-add:hover { background-color: #e6e6e6; }
        .btn-remove { background-color: #ffeef0; color: #d73a49; border: 1px solid #d73a49; padding: 0 8px; height: 34px; display: flex; align-items: center; justify-content: center; font-weight: bold;}

        .radio-group { display: flex; gap: 15px; background: #f8f9fa; padding: 10px; border-radius: 4px; border: 1px solid #eee; margin-bottom: 10px; }
        .radio-label { display: flex; align-items: center; cursor: pointer; font-size: 0.95em; }
        .radio-label input { margin-right: 6px; }

        .madhat-summary { 
            background: #e8f5e9; border: 1px solid #c8e6c9; border-radius: 6px; 
            padding: 15px; margin-top: 20px; text-align: center; color: #1b5e20;
        }
        .price-range { font-size: 1.5em; font-weight: bold; display: block; margin: 5px 0; }
        .price-note { font-size: 0.8em; color: #2e7d32; }

        .sub-type-wrap { display: none; margin-bottom: 15px; padding: 0 5px; animation: fadeIn 0.3s; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        .madhat-alert { padding: 10px; margin-bottom: 15px; background: #d4edda; color: #155724; border-radius: 4px; font-size: 0.9em; text-align: center; }
        .madhat-honey { display: none !important; }
    </style>

    <div class="madhat-wrapper">
        <?php if (isset($_GET['madhat_sent']) && $_GET['madhat_sent'] == '1'): ?>
            <div class="madhat-alert">
                <strong>Päring edukalt saadetud!</strong><br>
                Vaatame andmed üle ja võtame teiega varsti ühendust.
            </div>
        <?php endif; ?>

        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" enctype="multipart/form-data" id="madhatForm">
            <input type="hidden" name="action" value="madhat_submit_form">
            <?php wp_nonce_field('madhat_verify', 'madhat_nonce'); ?>
            <input type="text" name="madhat_robot_check" class="madhat-honey" value="">

            <div class="madhat-header"><h3>Hinnapäring & Lõikus</h3></div>

            <div class="madhat-row">
                <div class="madhat-col"><label class="madhat-label">Nimi</label><input type="text" class="madhat-input" name="client_name" required></div>
                <div class="madhat-col"><label class="madhat-label">E-post</label><input type="email" class="madhat-input" name="client_email" required></div>
            </div>
            <div class="madhat-row">
                <div class="madhat-col"><label class="madhat-label">Telefon</label><input type="tel" class="madhat-input" name="client_phone" required></div>
                <div class="madhat-col"><label class="madhat-label">Asukoht</label><input type="text" class="madhat-input" name="client_location"></div>
            </div>

            <hr style="border: 0; border-top: 1px solid #eee; margin: 15px 0;">

            <label class="madhat-label">Vali materjali tüüp:</label>
            <div class="radio-group">
                <label class="radio-label"><input type="radio" name="material_type" value="aknakile" required onchange="toggleSubType()"> Aknakiled</label>
                <label class="radio-label"><input type="radio" name="material_type" value="sisustuskile" required onchange="toggleSubType()"> Sisustuskiled</label>
            </div>

            <div id="sub-type-container" class="sub-type-wrap">
                <label class="madhat-label">Täpsusta kile tüüp:</label>
                <select name="material_sub_type" class="madhat-select" id="sub_type_select">
                </select>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:5px;">
                <label class="madhat-label">Mõõdud (mm)</label>
                <small style="color:#666; font-size:0.75em;">Ümardame automaatselt cm täpsuseni</small>
            </div>
            
            <div id="madhat-rows">
                <div class="madhat-row item-row">
                    <div class="madhat-col"><input type="number" class="madhat-input input-w" name="items[0][w]" placeholder="Laius" required onchange="calcPrice(this)"></div>
                    <div class="madhat-col"><input type="number" class="madhat-input input-h" name="items[0][h]" placeholder="Kõrgus" required onchange="calcPrice(this)"></div>
                    <div class="madhat-col" style="flex:0.6"><input type="number" class="madhat-input input-q" name="items[0][q]" value="1" required onchange="calcPrice()"></div>
                    <div class="madhat-col"><input type="text" class="madhat-input" name="items[0][l]" placeholder="Nimetus"></div>
                </div>
            </div>
            <button type="button" class="madhat-btn btn-add" onclick="addMRow()">+ Lisa rida</button>

            <div class="madhat-summary" id="price-box" style="display:none;">
                <span>Hinnanguline maksumus:</span>
                <span class="price-range" id="price-display">0€ - 0€</span>
                <span class="price-note">+ käibemaks (materjal + töö)</span>
            </div>

            <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">

            <div class="madhat-row">
                <div class="madhat-col">
                    <label class="madhat-label">Lisainfo</label>
                    <textarea name="client_info" class="madhat-textarea" rows="2"></textarea>
                </div>
            </div>
            <div class="madhat-row">
                <div class="madhat-col">
                    <label class="madhat-label">Failid/Pildid</label>
                    <input type="file" name="client_files[]" multiple style="font-size:0.85em;">
                </div>
            </div>

            <div style="margin-top:20px;">
                <button type="submit" class="madhat-btn" style="width:100%; padding:12px;">Saada päring</button>
                <p style="text-align:center; font-size:0.8em; color:#888; margin-top:10px;">Võtame ühendust esimesel võimalusel!</p>
            </div>
        </form>
    </div>

    <script>
    // --- SEADISTUSED ---
    const PRICE_MIN = 45; 
    const PRICE_MAX = 65;
    
    const SUB_TYPES = {
        'aknakile': ['Peegelkile', 'Nanokile', 'Turvakile', 'Toonkile', 'Muu'],
        'sisustuskile': ['Puitimitatsioon', 'Kiviimitatsioon', 'Värviline matt', 'Nahkimitatsioon', 'Muu']
    };

    function toggleSubType() {
        const type = document.querySelector('input[name="material_type"]:checked').value;
        const container = document.getElementById('sub-type-container');
        const select = document.getElementById('sub_type_select');
        
        select.innerHTML = ''; 
        
        if (SUB_TYPES[type]) {
            SUB_TYPES[type].forEach(opt => {
                const option = document.createElement('option');
                option.value = opt;
                option.text = opt;
                select.appendChild(option);
            });
            container.style.display = 'block';
        } else {
            container.style.display = 'none';
        }
        calcPrice();
    }

    function roundToCm(el) {
        if(!el.value) return;
        let val = parseInt(el.value);
        let rounded = Math.round(val / 10) * 10;
        el.value = rounded;
    }

    function calcPrice(el) {
        if (el) roundToCm(el);

        let totalSqM = 0;
        const rows = document.querySelectorAll('.item-row');
        
        rows.forEach(row => {
            const w = row.querySelector('.input-w').value;
            const h = row.querySelector('.input-h').value;
            const q = row.querySelector('.input-q').value;

            if (w && h && q) {
                const area = (w / 1000) * (h / 1000) * q;
                totalSqM += area;
            }
        });

        const box = document.getElementById('price-box');
        const display = document.getElementById('price-display');

        if (totalSqM > 0) {
            const minCost = Math.round(totalSqM * PRICE_MIN);
            const maxCost = Math.round(totalSqM * PRICE_MAX);
            
            display.textContent = `${minCost}€ - ${maxCost}€`;
            box.style.display = 'block';
        } else {
            box.style.display = 'none';
        }
    }

    let rIdx = 1;
    function addMRow() {
        const div = document.createElement('div');
        div.className = 'madhat-row item-row';
        div.innerHTML = `
            <div class="madhat-col"><input type="number" class="madhat-input input-w" name="items[${rIdx}][w]" placeholder="Laius" required onchange="calcPrice(this)"></div>
            <div class="madhat-col"><input type="number" class="madhat-input input-h" name="items[${rIdx}][h]" placeholder="Kõrgus" required onchange="calcPrice(this)"></div>
            <div class="madhat-col" style="flex:0.6"><input type="number" class="madhat-input input-q" name="items[${rIdx}][q]" value="1" required onchange="calcPrice()"></div>
            <div class="madhat-col"><input type="text" class="madhat-input" name="items[${rIdx}][l]" placeholder="Nimetus"></div>
            <button type="button" class="madhat-btn btn-remove" onclick="this.parentElement.remove(); calcPrice();">X</button>
        `;
        document.getElementById('madhat-rows').appendChild(div);
        rIdx++;
    }
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('madhat_form', 'madhat_render_form');

// ---------------------------------------------------------
// 2. TÖÖTLEMINE
// ---------------------------------------------------------
function madhat_handle_submit() {
    if (!isset($_POST['madhat_nonce']) || !wp_verify_nonce($_POST['madhat_nonce'], 'madhat_verify')) wp_die('Turvaviga.');
    if (!empty($_POST['madhat_robot_check'])) wp_die('Spämm.');

    $name = sanitize_text_field($_POST['client_name']);
    $email = sanitize_email($_POST['client_email']);
    $phone = sanitize_text_field($_POST['client_phone']);
    $loc = sanitize_text_field($_POST['client_location']);
    $info = sanitize_textarea_field($_POST['client_info']);
    
    $type = sanitize_text_field($_POST['material_type']);
    $sub_type = sanitize_text_field($_POST['material_sub_type']);
    
    $items = isset($_POST['items']) ? $_POST['items'] : [];

    $offset = ($type === 'sisustuskile') ? 80 : 20;
    
    $csv = "\xEF\xBB\xBFLength,Width,Qty,Label,Enabled\n";
    
    $mail_txt = "UUS PÄRING VEEBILEHELT\n\n";
    $mail_txt .= "Klient: $name\nEmail: $email\nTelefon: $phone\nAsukoht: $loc\n";
    $mail_txt .= "Materjal: $type ($sub_type)\n";
    $mail_txt .= "Lisainfo: $info\n\n";
    $mail_txt .= "SOOVITUD MÕÕDUD (Ümardatud 10mm täpsusega):\n------------------------------------\n";

    if (is_array($items)) {
        foreach ($items as $i) {
            $w_raw = floatval($i['w']);
            $h_raw = floatval($i['h']);
            
            $w = round($w_raw / 10) * 10;
            $h = round($h_raw / 10) * 10;
            
            $q = intval($i['q']);
            $l = sanitize_text_field($i['l']);

            if ($w <= 0 || $h <= 0 || $q <= 0) continue;

            $mail_txt .= "- $l: $w x $h mm ($q tk)\n";

            $csv_w = $w + $offset;
            $csv_h = $h + $offset;
            $l_csv = str_replace('"', '""', $l); 
            $csv .= "$csv_h,$csv_w,$q,\"$l_csv\",true\n";
        }
    }

    $attachments = [];
    $upload = wp_upload_dir();
    $filename = 'cutlist_' . date('ymd_Hi') . '_' . sanitize_file_name($name) . '.csv';
    $csv_path = $upload['path'] . '/' . $filename;
    
    $f = fopen($csv_path, 'w');
    if ($f) { fwrite($f, $csv); fclose($f); $attachments[] = $csv_path; }

    if (!empty($_FILES['client_files']['name'][0])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        foreach ($_FILES['client_files']['name'] as $k => $v) {
            if ($_FILES['client_files']['name'][$k]) {
                $file = [
                    'name' => sanitize_file_name($_FILES['client_files']['name'][$k]),
                    'type' => $_FILES['client_files']['type'][$k],
                    'tmp_name' => $_FILES['client_files']['tmp_name'][$k],
                    'error' => $_FILES['client_files']['error'][$k],
                    'size' => $_FILES['client_files']['size'][$k]
                ];
                $up = wp_handle_upload($file, ['test_form' => false]);
                if ($up && !isset($up['error'])) $attachments[] = $up['file'];
            }
        }
    }

    $to = 'arendus@veebmik.ee'; 
    $headers = ['Content-Type: text/plain; charset=UTF-8', 'From: Madhat Kalkulaator <wordpress@' . $_SERVER['SERVER_NAME'] . '>'];
    
    wp_mail($to, "Päring: $name ($type)", $mail_txt, $headers, $attachments);

    wp_redirect(add_query_arg('madhat_sent', '1', wp_get_referer()));
    exit;
}
add_action('admin_post_madhat_submit_form', 'madhat_handle_submit');
add_action('admin_post_nopriv_madhat_submit_form', 'madhat_handle_submit');