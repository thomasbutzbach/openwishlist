<?php
use OpenWishlist\Support\Version;
?>

<nav style="margin-bottom: 1rem;">
    <ul>
        <li><a href="/admin">Dashboard</a></li>
        <li><a href="/admin/jobs">Jobs</a></li>
        <li><strong><a href="/admin/settings" style="text-decoration: none;">Settings</a></strong></li>
    </ul>
</nav>

<hgroup>
    <h1>Settings <small style="color: #666; font-weight: normal;"><?= Version::formatDisplay() ?></small></h1>
    <p>Application configuration</p>
</hgroup>

<?php if (isset($error)): ?>
    <article style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;">
        <strong>Error:</strong> <?= htmlspecialchars($error) ?>
    </article>
<?php endif; ?>

<div id="success-message" style="display: none; background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 1rem; margin-bottom: 1rem; border-radius: 4px;">
    Settings saved successfully!
</div>

<div id="error-message" style="display: none; background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 1rem; margin-bottom: 1rem; border-radius: 4px;">
</div>

<form id="settings-form" onsubmit="saveSettings(event)">
    <div class="grid">
        <article>
            <header><strong>Application Settings</strong></header>
            
            <label for="app_name">Application Name</label>
            <input type="text" 
                   id="app_name" 
                   name="app_name" 
                   value="<?= htmlspecialchars($settings['app_name'] ?? 'OpenWishlist') ?>"
                   placeholder="OpenWishlist"
                   required>
            <small>Displayed in the header and page titles</small>
            
            <label>
                <input type="checkbox" 
                       id="public_registration" 
                       name="public_registration" 
                       <?= ($settings['public_registration'] ?? true) ? 'checked' : '' ?>>
                Allow public registration
            </label>
            <small>If disabled, only admins can create new user accounts</small>
        </article>
        
        <article>
            <header><strong>File Upload Settings</strong></header>
            
            <label for="max_file_size">Maximum File Size (bytes)</label>
            <input type="number" 
                   id="max_file_size" 
                   name="max_file_size" 
                   value="<?= $settings['max_file_size'] ?? 5242880 ?>"
                   min="1024"
                   max="52428800"
                   required>
            <small>
                Current: <?= formatBytes($settings['max_file_size'] ?? 5242880) ?> 
                (Max: 50MB)
            </small>
            
            <label for="allowed_domains">Allowed Image Domains</label>
            <textarea id="allowed_domains" 
                      name="allowed_domains" 
                      rows="3" 
                      placeholder="example.com&#10;images.example.net&#10;cdn.example.org"><?= htmlspecialchars(implode("\n", $settings['allowed_domains'] ?? [])) ?></textarea>
            <small>One domain per line. Leave empty to allow all domains.</small>
        </article>
    </div>
    
    <div style="margin-top: 2rem;">
        <button type="submit" id="save-button">Save Settings</button>
    </div>
</form>

<?php
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>

<script>
let originalSettings = {};

// Store original settings for reset functionality
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('settings-form');
    const formData = new FormData(form);
    
    originalSettings = {
        app_name: formData.get('app_name'),
        public_registration: document.getElementById('public_registration').checked,
        max_file_size: parseInt(formData.get('max_file_size')),
        allowed_domains: formData.get('allowed_domains').split('\n').filter(d => d.trim())
    };
});

async function saveSettings(event) {
    event.preventDefault();
    
    const form = document.getElementById('settings-form');
    const saveButton = document.getElementById('save-button');
    const successMsg = document.getElementById('success-message');
    const errorMsg = document.getElementById('error-message');
    
    // Hide messages
    successMsg.style.display = 'none';
    errorMsg.style.display = 'none';
    
    // Disable button
    saveButton.disabled = true;
    saveButton.textContent = 'Saving...';
    
    try {
        const formData = new FormData(form);
        
        const settings = {
            app_name: formData.get('app_name'),
            public_registration: document.getElementById('public_registration').checked,
            max_file_size: parseInt(formData.get('max_file_size')),
            allowed_domains: formData.get('allowed_domains')
                .split('\n')
                .map(d => d.trim())
                .filter(d => d.length > 0)
        };
        
        const response = await fetch('/api/admin/settings', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-Csrf-Token': '<?= \OpenWishlist\Support\Csrf::token() ?>'
            },
            body: JSON.stringify(settings)
        });
        
        if (response.ok) {
            successMsg.style.display = 'block';
            // Update original settings for reset
            originalSettings = {...settings};
        } else {
            const error = await response.json();
            errorMsg.textContent = error.detail || 'Failed to save settings';
            errorMsg.style.display = 'block';
        }
    } catch (e) {
        errorMsg.textContent = 'Network error: Failed to save settings';
        errorMsg.style.display = 'block';
    } finally {
        saveButton.disabled = false;
        saveButton.textContent = 'Save Settings';
    }
}

</script>