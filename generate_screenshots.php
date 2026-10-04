<?php

declare(strict_types=1);

/**
 * Generate 20 high-fidelity SVG visual screenshots and update package READMEs.
 */

$baseDir = __DIR__;

function wrapSvg(string $title, string $url, string $content, int $width = 800, int $height = 460): string
{
    $wBox = $width - 30;
    $hBox = $height - 30;
    $hPathRight = $width - 25;
    $hLineRight = $width - 15;
    $addrW = $width - 360;
    $addrX = 130;
    $addrTextX = $addrX + ($addrW / 2);
    $titleX = $width - 35;

    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$width} {$height}" width="{$width}" height="{$height}" style="background: transparent; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
  <defs>
    <filter id="winShadow" x="-5%" y="-5%" width="110%" height="110%">
      <feDropShadow dx="0" dy="6" stdDeviation="10" flood-color="#000000" flood-opacity="0.4"/>
    </filter>
    <linearGradient id="winTitleGrad" x1="0" y1="0" x2="1" y2="0">
      <stop offset="0%" stop-color="#1e293b"/>
      <stop offset="100%" stop-color="#0f172a"/>
    </linearGradient>
  </defs>

  <!-- Window Container -->
  <g filter="url(#winShadow)">
    <rect x="15" y="15" width="{$wBox}" height="{$hBox}" rx="8" fill="#0f172a" stroke="#334155" stroke-width="1.5"/>
    
    <!-- Titlebar -->
    <path d="M 15 23 A 8 8 0 0 1 23 15 L {$hPathRight} 15 A 8 8 0 0 1 {$hLineRight} 23 L {$hLineRight} 50 L 15 50 Z" fill="url(#winTitleGrad)"/>
    <line x1="15" y1="50" x2="{$hLineRight}" y2="50" stroke="#334155" stroke-width="1"/>
    
    <!-- Window Controls (macOS style) -->
    <circle cx="34" cy="32" r="5" fill="#ef4444"/>
    <circle cx="50" cy="32" r="5" fill="#f59e0b"/>
    <circle cx="66" cy="32" r="5" fill="#10b981"/>
    
    <!-- Address Bar -->
    <rect x="{$addrX}" y="23" width="{$addrW}" height="19" rx="4" fill="#020617" stroke="#1e293b" stroke-width="1"/>
    <text x="{$addrTextX}" y="36.5" fill="#94a3b8" font-size="10.5" text-anchor="middle" font-family="monospace">{$url}</text>

    <!-- Window Title -->
    <text x="{$titleX}" y="36" fill="#64748b" font-size="11" text-anchor="end" font-weight="600">{$title}</text>
  </g>

  <!-- Body Content -->
  <g transform="translate(15, 50)">
{$content}
  </g>
</svg>
SVG;
}

$generators = [];

// 1. Activity Log
$generators['activity-log'] = function (): string {
    $content = <<<SVG
    <!-- Background & Toolbar -->
    <rect x="0" y="0" width="770" height="395" fill="#0b1120"/>
    <rect x="20" y="15" width="730" height="42" rx="6" fill="#1e293b" stroke="#334155" stroke-width="1"/>
    <text x="35" y="41" fill="#f8fafc" font-size="13" font-weight="600">Audit Trail &amp; Activity Log</text>
    
    <!-- Filter pills -->
    <rect x="460" y="24" width="70" height="24" rx="4" fill="#334155"/>
    <text x="495" y="40" fill="#e2e8f0" font-size="11" text-anchor="middle">All Logs ▾</text>
    <rect x="540" y="24" width="100" height="24" rx="4" fill="#334155"/>
    <text x="590" y="40" fill="#e2e8f0" font-size="11" text-anchor="middle">Last 30 Days ▾</text>
    <rect x="650" y="24" width="85" height="24" rx="4" fill="#0284c7"/>
    <text x="692" y="40" fill="#ffffff" font-size="11" text-anchor="middle" font-weight="600">Export CSV</text>

    <!-- Table Header -->
    <rect x="20" y="70" width="730" height="30" fill="#1e293b"/>
    <text x="35" y="90" fill="#94a3b8" font-size="10.5" font-weight="600">EVENT / LOG</text>
    <text x="145" y="90" fill="#94a3b8" font-size="10.5" font-weight="600">DESCRIPTION</text>
    <text x="430" y="90" fill="#94a3b8" font-size="10.5" font-weight="600">CAUSED BY</text>
    <text x="560" y="90" fill="#94a3b8" font-size="10.5" font-weight="600">IP ADDRESS</text>
    <text x="670" y="90" fill="#94a3b8" font-size="10.5" font-weight="600">TIME</text>

    <!-- Row 1 -->
    <rect x="20" y="105" width="730" height="52" fill="#0f172a" rx="4" stroke="#1e293b"/>
    <rect x="35" y="120" width="85" height="22" rx="4" fill="#065f46"/>
    <text x="77" y="135" fill="#34d399" font-size="10.5" text-anchor="middle" font-weight="600">auth.login</text>
    <text x="145" y="128" fill="#f8fafc" font-size="12" font-weight="500">Administrator signed into dashboard</text>
    <text x="145" y="145" fill="#64748b" font-size="10.5">Session authenticated via Web Guard</text>
    <text x="430" y="132" fill="#38bdf8" font-size="11.5" font-weight="500">admin@bongo.com</text>
    <text x="430" y="147" fill="#64748b" font-size="10">Super Admin (ID: 1)</text>
    <text x="560" y="136" fill="#cbd5e1" font-size="11" font-family="monospace">103.145.23.4</text>
    <text x="670" y="136" fill="#94a3b8" font-size="11">2 mins ago</text>

    <!-- Row 2 -->
    <rect x="20" y="165" width="730" height="52" fill="#0f172a" rx="4" stroke="#1e293b"/>
    <rect x="35" y="180" width="85" height="22" rx="4" fill="#1e3a8a"/>
    <text x="77" y="195" fill="#60a5fa" font-size="10.5" text-anchor="middle" font-weight="600">order.placed</text>
    <text x="145" y="188" fill="#f8fafc" font-size="12" font-weight="500">Order #ORD-1092 placed (৳ 4,500.00)</text>
    <text x="145" y="205" fill="#64748b" font-size="10.5">Customer verified payment via bKash</text>
    <text x="430" y="192" fill="#38bdf8" font-size="11.5" font-weight="500">rahim@example.com</text>
    <text x="430" y="207" fill="#64748b" font-size="10">Customer (ID: 42)</text>
    <text x="560" y="196" fill="#cbd5e1" font-size="11" font-family="monospace">118.179.88.12</text>
    <text x="670" y="196" fill="#94a3b8" font-size="11">14 mins ago</text>

    <!-- Row 3 -->
    <rect x="20" y="225" width="730" height="52" fill="#0f172a" rx="4" stroke="#1e293b"/>
    <rect x="35" y="240" width="85" height="22" rx="4" fill="#78350f"/>
    <text x="77" y="255" fill="#f59e0b" font-size="10.5" text-anchor="middle" font-weight="600">product.edit</text>
    <text x="145" y="248" fill="#f8fafc" font-size="12" font-weight="500">Product price changed: ৳ 85,000 → ৳ 82,500</text>
    <text x="145" y="265" fill="#64748b" font-size="10.5">Subject: Product #4 ("Gaming Laptop Ultra")</text>
    <text x="430" y="252" fill="#38bdf8" font-size="11.5" font-weight="500">karim@bongo.com</text>
    <text x="430" y="267" fill="#64748b" font-size="10">Store Editor (ID: 5)</text>
    <text x="560" y="256" fill="#cbd5e1" font-size="11" font-family="monospace">103.145.23.4</text>
    <text x="670" y="256" fill="#94a3b8" font-size="11">1 hour ago</text>

    <!-- Row 4 -->
    <rect x="20" y="285" width="730" height="52" fill="#0f172a" rx="4" stroke="#1e293b"/>
    <rect x="35" y="300" width="85" height="22" rx="4" fill="#581c87"/>
    <text x="77" y="315" fill="#c084fc" font-size="10.5" text-anchor="middle" font-weight="600">role.assigned</text>
    <text x="145" y="308" fill="#f8fafc" font-size="12" font-weight="500">Role "Store Manager" granted to user</text>
    <text x="145" y="325" fill="#64748b" font-size="10.5">Permissions updated: manage-orders, issue-refunds</text>
    <text x="430" y="312" fill="#38bdf8" font-size="11.5" font-weight="500">admin@bongo.com</text>
    <text x="430" y="327" fill="#64748b" font-size="10">Super Admin (ID: 1)</text>
    <text x="560" y="316" fill="#cbd5e1" font-size="11" font-family="monospace">103.145.23.4</text>
    <text x="670" y="316" fill="#94a3b8" font-size="11">3 hours ago</text>

    <!-- Footer Stats -->
    <rect x="20" y="347" width="730" height="34" rx="4" fill="#1e293b"/>
    <text x="35" y="369" fill="#94a3b8" font-size="11">Showing 4 of 1,280 activities • Retention: 30 days auto-clean</text>
    <text x="735" y="369" fill="#38bdf8" font-size="11" text-anchor="end">php bongo activity:clean</text>
SVG;
    return wrapSvg('Activity Log', 'https://app.test/admin/activities', $content);
};

// 2. API Tools
$generators['api-tools'] = function (): string {
    $content = <<<SVG
    <rect x="0" y="0" width="770" height="395" fill="#0f172a"/>
    
    <!-- Swagger Header -->
    <rect x="20" y="15" width="730" height="50" rx="6" fill="#1e293b" stroke="#334155" stroke-width="1"/>
    <text x="35" y="40" fill="#f8fafc" font-size="15" font-weight="700">Bongo Storefront API</text>
    <rect x="210" y="27" width="45" height="18" rx="4" fill="#0284c7"/>
    <text x="232" y="40" fill="#ffffff" font-size="10" font-weight="600" text-anchor="middle">v1.0.0</text>
    <rect x="260" y="27" width="60" height="18" rx="4" fill="#334155"/>
    <text x="290" y="40" fill="#94a3b8" font-size="10" font-weight="600" text-anchor="middle">OAS 3.0</text>

    <rect x="630" y="27" width="105" height="26" rx="4" fill="#10b981"/>
    <text x="682" y="44" fill="#ffffff" font-size="11" font-weight="600" text-anchor="middle">Authorize 🔒</text>

    <!-- Endpoint 1: GET /api/v1/products -->
    <rect x="20" y="78" width="730" height="42" rx="4" fill="#064e3b" stroke="#059669" stroke-width="1"/>
    <rect x="30" y="85" width="55" height="28" rx="3" fill="#10b981"/>
    <text x="57" y="103" fill="#ffffff" font-size="12" font-weight="700" text-anchor="middle">GET</text>
    <text x="98" y="104" fill="#f8fafc" font-size="13" font-weight="600" font-family="monospace">/api/v1/products</text>
    <text x="360" y="103" fill="#a7f3d0" font-size="11.5">List paginated catalog products with filters</text>
    <text x="730" y="103" fill="#34d399" font-size="11" text-anchor="end">Try it out ▾</text>

    <!-- Expanded Section for Endpoint 1 -->
    <rect x="20" y="120" width="730" height="135" fill="#022c22" stroke="#059669" stroke-width="1"/>
    <text x="35" y="142" fill="#6ee7b7" font-size="11" font-weight="600">QUERY PARAMETERS</text>
    <text x="35" y="162" fill="#cbd5e1" font-size="11" font-family="monospace">category_id</text>
    <text x="140" y="162" fill="#94a3b8" font-size="11">integer (optional, e.g. 3)</text>
    <text x="35" y="180" fill="#cbd5e1" font-size="11" font-family="monospace">per_page</text>
    <text x="140" y="180" fill="#94a3b8" font-size="11">integer (default: 15, max: 100)</text>

    <text x="380" y="142" fill="#6ee7b7" font-size="11" font-weight="600">RESPONSE (200 OK)</text>
    <rect x="380" y="152" width="355" height="92" rx="4" fill="#0b1120" stroke="#059669" stroke-width="0.5"/>
    <text x="390" y="170" fill="#38bdf8" font-size="10" font-family="monospace">{"status": "success", "data": [</text>
    <text x="405" y="185" fill="#e2e8f0" font-size="10" font-family="monospace">{"id": 1, "name": "Wireless Mouse", "price": 1200},</text>
    <text x="405" y="200" fill="#e2e8f0" font-size="10" font-family="monospace">{"id": 2, "name": "Mechanical Keyboard", "price": 4500}</text>
    <text x="390" y="215" fill="#38bdf8" font-size="10" font-family="monospace">], "meta": {"page": 1, "total": 42}}</text>

    <!-- Endpoint 2: POST /api/v1/orders -->
    <rect x="20" y="265" width="730" height="42" rx="4" fill="#1e3a8a" stroke="#2563eb" stroke-width="1"/>
    <rect x="30" y="272" width="55" height="28" rx="3" fill="#3b82f6"/>
    <text x="57" y="290" fill="#ffffff" font-size="12" font-weight="700" text-anchor="middle">POST</text>
    <text x="98" y="291" fill="#f8fafc" font-size="13" font-weight="600" font-family="monospace">/api/v1/orders</text>
    <text x="360" y="290" fill="#bfdbfe" font-size="11.5">Place new order and generate payment token</text>
    <text x="730" y="290" fill="#93c5fd" font-size="11" text-anchor="end">Authorize 🔒</text>

    <!-- Endpoint 3: GET /api/v1/health -->
    <rect x="20" y="317" width="730" height="42" rx="4" fill="#064e3b" stroke="#059669" stroke-width="1"/>
    <rect x="30" y="324" width="55" height="28" rx="3" fill="#10b981"/>
    <text x="57" y="342" fill="#ffffff" font-size="12" font-weight="700" text-anchor="middle">GET</text>
    <text x="98" y="343" fill="#f8fafc" font-size="13" font-weight="600" font-family="monospace">/api/v1/health</text>
    <text x="360" y="342" fill="#a7f3d0" font-size="11.5">System uptime and diagnostics health report</text>
    <text x="730" y="342" fill="#34d399" font-size="11" text-anchor="end">Try it out ▾</text>

    <text x="385" y="380" fill="#64748b" font-size="10.5" text-anchor="middle">Interactive Swagger UI &amp; Redoc generated with zero dependencies • /docs &amp; /docs/redoc</text>
SVG;
    return wrapSvg('API Tools - Swagger UI', 'https://app.test/docs', $content);
};

// 3. Backup
$generators['backup'] = function (): string {
    $content = <<<SVG
    <rect x="0" y="0" width="770" height="395" fill="#090d16"/>
    
    <!-- Terminal Header -->
    <rect x="20" y="15" width="730" height="365" rx="6" fill="#020617" stroke="#1e293b" stroke-width="1"/>
    
    <!-- CLI Prompt -->
    <text x="40" y="48" fill="#38bdf8" font-size="13" font-weight="600" font-family="monospace">user@bongo-server:~$</text>
    <text x="215" y="48" fill="#f8fafc" font-size="13" font-family="monospace">php bongo backup:run --disk=s3</text>

    <!-- Execution Output -->
    <text x="40" y="80" fill="#94a3b8" font-size="12" font-family="monospace">[2026-10-04 18:45:12] Starting automated full application backup...</text>
    
    <text x="40" y="110" fill="#22c55e" font-size="12" font-family="monospace">✔ [1/4] Database Dump</text>
    <text x="230" y="110" fill="#cbd5e1" font-size="12" font-family="monospace">Exported MySQL 8.0 schema &amp; data (14.8 MB in 1.2s)</text>

    <text x="40" y="140" fill="#22c55e" font-size="12" font-family="monospace">✔ [2/4] Asset Archiving</text>
    <text x="230" y="140" fill="#cbd5e1" font-size="12" font-family="monospace">Archived storage/app &amp; public/storage (32.4 MB, 1,240 files)</text>

    <text x="40" y="170" fill="#22c55e" font-size="12" font-family="monospace">✔ [3/4] ZIP Compression</text>
    <text x="230" y="170" fill="#cbd5e1" font-size="12" font-family="monospace">Created bongo_backup_2026-10-04_184512.zip (41.2 MB)</text>

    <text x="40" y="200" fill="#22c55e" font-size="12" font-family="monospace">✔ [4/4] Cloud Upload</text>
    <text x="230" y="200" fill="#cbd5e1" font-size="12" font-family="monospace">Uploaded to S3 bucket [my-production-backups] via SigV4</text>

    <!-- Retention policy line -->
    <text x="40" y="235" fill="#f59e0b" font-size="12" font-family="monospace">ℹ [Retention Policy]</text>
    <text x="230" y="235" fill="#cbd5e1" font-size="12" font-family="monospace">Cleaned 2 older archives exceeding 30-day retention</text>

    <!-- Success Box -->
    <rect x="40" y="260" width="690" height="45" rx="4" fill="#064e3b" stroke="#059669" stroke-width="1"/>
    <text x="60" y="287" fill="#6ee7b7" font-size="13" font-weight="700" font-family="monospace">[SUCCESS] Full backup completed successfully in 3.82s (Final size: 41.2 MB)</text>

    <!-- Prompt 2 -->
    <text x="40" y="340" fill="#38bdf8" font-size="13" font-weight="600" font-family="monospace">user@bongo-server:~$</text>
    <text x="215" y="340" fill="#f8fafc" font-size="13" font-family="monospace">php bongo backup:list</text>
    <text x="40" y="365" fill="#64748b" font-size="11" font-family="monospace">  2026-10-04_184512.zip (41.2 MB) | 2026-10-03_020000.zip (40.8 MB) | 2026-10-02_020000.zip (39.5 MB)</text>
SVG;
    return wrapSvg('CLI Backup Runner', 'terminal://bongo-cli/backup', $content);
};

// 4. bKash
$generators['bkash'] = function (): string {
    $content = <<<SVG
    <rect x="0" y="0" width="770" height="395" fill="#18181b"/>
    
    <!-- Modal Container -->
    <rect x="185" y="25" width="400" height="345" rx="10" fill="#ffffff" filter="drop-shadow(0 10px 25px rgba(0,0,0,0.5))"/>
    
    <!-- bKash Header Pink -->
    <path d="M 185 35 A 10 10 0 0 1 195 25 L 575 25 A 10 10 0 0 1 585 35 L 585 90 L 185 90 Z" fill="#D12053"/>
    <text x="385" y="60" fill="#ffffff" font-size="20" font-weight="800" text-anchor="middle">bKash</text>
    <text x="385" y="78" fill="#fce7f3" font-size="11" text-anchor="middle">Tokenized Checkout PGW</text>

    <!-- Order Info -->
    <rect x="205" y="105" width="360" height="52" rx="6" fill="#fdf2f8" stroke="#fbcfe8" stroke-width="1"/>
    <text x="220" y="125" fill="#831843" font-size="11" font-weight="600">Merchant: Bongo Shop Ltd</text>
    <text x="220" y="143" fill="#64748b" font-size="10.5">Invoice: BNG-2026-8812</text>
    <text x="550" y="136" fill="#D12053" font-size="16" font-weight="700" text-anchor="end">৳ 3,250.00</text>

    <!-- Phone Number Input -->
    <text x="205" y="180" fill="#374151" font-size="12" font-weight="600">Your bKash Account Number</text>
    <rect x="205" y="190" width="360" height="40" rx="6" fill="#ffffff" stroke="#e5e7eb" stroke-width="1.5"/>
    <rect x="206" y="191" width="55" height="38" rx="5" fill="#f3f4f6"/>
    <text x="233" y="215" fill="#4b5563" font-size="12" font-weight="600" text-anchor="middle">+880</text>
    <text x="275" y="215" fill="#111827" font-size="13" font-family="monospace">1712 345678</text>

    <!-- OTP Step -->
    <text x="205" y="250" fill="#374151" font-size="11" font-weight="500">A 6-digit OTP will be sent to this number</text>

    <!-- Buttons -->
    <rect x="205" y="275" width="170" height="42" rx="6" fill="#e5e7eb"/>
    <text x="290" y="301" fill="#4b5563" font-size="13" font-weight="600" text-anchor="middle">CLOSE</text>

    <rect x="395" y="275" width="170" height="42" rx="6" fill="#D12053"/>
    <text x="480" y="301" fill="#ffffff" font-size="13" font-weight="700" text-anchor="middle">CONFIRM 🔒</text>

    <text x="385" y="345" fill="#9ca3af" font-size="10" text-anchor="middle">Secured with bKash Tokenized API v1.2.0 • 256-Bit SSL</text>
SVG;
    return wrapSvg('bKash Payment Checkout', 'https://checkout.pay.bka.sh/v1.2.0/payment', $content);
};

// 5. File Manager
$generators['file-manager'] = function (): string {
    $content = <<<SVG
    <rect x="0" y="0" width="770" height="395" fill="#f8fafc"/>
    
    <!-- Top Dark Slate Header -->
    <rect x="0" y="0" width="770" height="48" fill="#2b3d51"/>
    <text x="20" y="29" fill="#ffffff" font-size="14" font-weight="700">UniSharp File Manager</text>

    <rect x="200" y="11" width="95" height="26" rx="4" fill="#3d5269"/>
    <text x="247" y="28" fill="#ffffff" font-size="11" text-anchor="middle">⊞ Thumbnails</text>

    <rect x="302" y="11" width="70" height="26" rx="4" fill="#243344"/>
    <text x="337" y="28" fill="#94a3b8" font-size="11" text-anchor="middle">≡ List</text>

    <rect x="380" y="11" width="85" height="26" rx="4" fill="#243344"/>
    <text x="422" y="28" fill="#94a3b8" font-size="11" text-anchor="middle">⇅ Sort ▾</text>

    <!-- Search Input -->
    <rect x="580" y="11" width="170" height="26" rx="4" fill="#1b2836"/>
    <text x="595" y="28" fill="#94a3b8" font-size="11">🔍 Search files...</text>

    <!-- Sidebar Directory Tree -->
    <rect x="0" y="48" width="190" height="347" fill="#ffffff" stroke="#e2e8f0" stroke-width="1"/>
    <text x="15" y="74" fill="#64748b" font-size="10.5" font-weight="700">ROOT DIRECTORIES</text>

    <text x="20" y="102" fill="#334155" font-size="12">📁 uploads</text>
    
    <!-- Active Folder Highlight -->
    <rect x="0" y="115" width="190" height="30" fill="#dfe4ea"/>
    <text x="35" y="135" fill="#1e293b" font-size="12" font-weight="700">📁 banners</text>

    <text x="35" y="165" fill="#475569" font-size="12">📁 products</text>
    <text x="35" y="195" fill="#475569" font-size="12">📁 users</text>
    <text x="35" y="225" fill="#475569" font-size="12">📁 documents</text>

    <!-- Main Workspace -->
    <!-- Breadcrumb -->
    <rect x="190" y="48" width="580" height="34" fill="#f1f5f9" stroke="#e2e8f0" stroke-width="1"/>
    <text x="205" y="70" fill="#475569" font-size="11.5">uploads / <tspan font-weight="700" fill="#0f172a">banners</tspan> (4 items)</text>

    <!-- Card 1: Subfolder -->
    <rect x="210" y="98" width="120" height="110" rx="6" fill="#ffffff" stroke="#cbd5e1" stroke-width="1"/>
    <path d="M 245 125 L 260 125 L 268 133 L 295 133 L 295 158 L 245 158 Z" fill="#38bdf8"/>
    <text x="270" y="180" fill="#1e293b" font-size="11" font-weight="600" text-anchor="middle">2026-campaigns</text>
    <text x="270" y="195" fill="#94a3b8" font-size="9.5" text-anchor="middle">Folder</text>

    <!-- Card 2: Selected Image (Teal border #16a085) -->
    <rect x="345" y="98" width="120" height="110" rx="6" fill="#ffffff" stroke="#16a085" stroke-width="2.5"/>
    <rect x="355" y="108" width="100" height="52" rx="3" fill="#cbd5e1"/>
    <text x="405" y="138" fill="#475569" font-size="10" text-anchor="middle">🌅 hero.webp</text>
    <text x="405" y="180" fill="#1e293b" font-size="11" font-weight="700" text-anchor="middle">hero.webp</text>
    <text x="405" y="195" fill="#16a085" font-size="9.5" font-weight="600" text-anchor="middle">1920×750 • 142 KB</text>

    <!-- Card 3: Code file -->
    <rect x="480" y="98" width="120" height="110" rx="6" fill="#ffffff" stroke="#cbd5e1" stroke-width="1"/>
    <rect x="515" y="115" width="50" height="35" rx="3" fill="#334155"/>
    <text x="540" y="137" fill="#38bdf8" font-size="10" font-weight="700" text-anchor="middle">&lt;/&gt;</text>
    <text x="540" y="180" fill="#1e293b" font-size="11" font-weight="600" text-anchor="middle">slider.js</text>
    <text x="540" y="195" fill="#94a3b8" font-size="9.5" text-anchor="middle">JavaScript • 4.8 KB</text>

    <!-- Card 4: Document -->
    <rect x="615" y="98" width="120" height="110" rx="6" fill="#ffffff" stroke="#cbd5e1" stroke-width="1"/>
    <rect x="650" y="115" width="50" height="35" rx="3" fill="#dc2626"/>
    <text x="675" y="137" fill="#ffffff" font-size="9" font-weight="700" text-anchor="middle">PDF</text>
    <text x="675" y="180" fill="#1e293b" font-size="11" font-weight="600" text-anchor="middle">guide.pdf</text>
    <text x="675" y="195" fill="#94a3b8" font-size="9.5" text-anchor="middle">PDF Doc • 1.2 MB</text>

    <!-- Floating Action Button (FAB) Speed Dial -->
    <circle cx="725" cy="340" r="24" fill="#16a085" filter="drop-shadow(0 4px 6px rgba(0,0,0,0.3))"/>
    <text x="725" y="347" fill="#ffffff" font-size="22" font-weight="700" text-anchor="middle">+</text>

    <rect x="625" y="328" width="80" height="24" rx="12" fill="#718096"/>
    <text x="665" y="344" fill="#ffffff" font-size="10.5" font-weight="600" text-anchor="middle">Upload</text>

    <!-- Bottom Bar -->
    <rect x="190" y="355" width="580" height="40" fill="#ffffff" stroke="#e2e8f0" stroke-width="1"/>
    <text x="210" y="379" fill="#16a085" font-size="12" font-weight="600">✔ hero.webp selected</text>
    <rect x="610" y="362" width="70" height="26" rx="4" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="1"/>
    <text x="645" y="379" fill="#334155" font-size="11" text-anchor="middle">Preview</text>
    <rect x="690" y="362" width="70" height="26" rx="4" fill="#16a085"/>
    <text x="725" y="379" fill="#ffffff" font-size="11" font-weight="700" text-anchor="middle">Confirm</text>
SVG;
    return wrapSvg('UniSharp File Manager', 'https://app.test/filemanager', $content);
};

// 6. FTP
$generators['ftp'] = function (): string {
    $content = <<<SVG
    <rect x="0" y="0" width="770" height="395" fill="#0f172a"/>
    
    <!-- Top Status Banner -->
    <rect x="20" y="15" width="730" height="45" rx="6" fill="#1e293b" stroke="#334155" stroke-width="1"/>
    <circle cx="40" cy="37" r="5" fill="#10b981"/>
    <text x="55" y="41" fill="#f8fafc" font-size="13" font-weight="600">FTP / FTPS Storage Driver (Active)</text>
    <text x="320" y="41" fill="#94a3b8" font-size="11" font-family="monospace">ftp.server.com:21 (Passive TLS 1.3)</text>
    <rect x="645" y="25" width="90" height="25" rx="4" fill="#0284c7"/>
    <text x="690" y="41" fill="#ffffff" font-size="11" font-weight="600" text-anchor="middle">Test Connect</text>

    <!-- Dual Pane Transfer View -->
    <!-- Pane 1: Local Disk -->
    <rect x="20" y="72" width="355" height="225" rx="6" fill="#1e293b" stroke="#334155" stroke-width="1"/>
    <rect x="20" y="72" width="355" height="30" rx="6" fill="#334155"/>
    <text x="35" y="92" fill="#f8fafc" font-size="11.5" font-weight="600">Local Disk (storage/app)</text>

    <text x="35" y="125" fill="#cbd5e1" font-size="11" font-family="monospace">📁 backups/</text>
    <text x="35" y="150" fill="#cbd5e1" font-size="11" font-family="monospace">📁 exports/</text>
    <text x="50" y="175" fill="#38bdf8" font-size="11" font-family="monospace">📄 monthly_sales.csv (14.2 MB)</text>
    <text x="50" y="200" fill="#e2e8f0" font-size="11" font-family="monospace">📄 catalog_sync.xml (2.8 MB)</text>
    <text x="35" y="225" fill="#cbd5e1" font-size="11" font-family="monospace">📁 invoices/</text>

    <!-- Pane 2: Remote FTP Server -->
    <rect x="395" y="72" width="355" height="225" rx="6" fill="#1e293b" stroke="#334155" stroke-width="1"/>
    <rect x="395" y="72" width="355" height="30" rx="6" fill="#334155"/>
    <text x="410" y="92" fill="#f8fafc" font-size="11.5" font-weight="600">Remote FTPS (/public_html/storage)</text>

    <text x="410" y="125" fill="#cbd5e1" font-size="11" font-family="monospace">📁 reports/</text>
    <text x="425" y="150" fill="#34d399" font-size="11" font-family="monospace">✔ monthly_sales.csv (Uploaded)</text>
    <text x="410" y="175" fill="#cbd5e1" font-size="11" font-family="monospace">📁 media/</text>
    <text x="410" y="200" fill="#cbd5e1" font-size="11" font-family="monospace">📁 archives/</text>
    <text x="425" y="225" fill="#94a3b8" font-size="11" font-family="monospace">📄 backup_2026-10-04.zip (41 MB)</text>

    <!-- Transfer Progress Bar -->
    <rect x="20" y="310" width="730" height="70" rx="6" fill="#020617" stroke="#1e293b" stroke-width="1"/>
    <text x="35" y="333" fill="#e2e8f0" font-size="11.5" font-weight="600">Transfer Queue: monthly_sales.csv → /public_html/storage/reports/</text>
    <rect x="35" y="345" width="700" height="8" rx="4" fill="#1e293b"/>
    <rect x="35" y="345" width="580" height="8" rx="4" fill="#10b981"/>
    <text x="35" y="370" fill="#94a3b8" font-size="10.5">Speed: 18.5 MB/s • 83% complete • Zero external dependencies (ext-ftp &amp; pure PHP sockets)</text>
SVG;
    return wrapSvg('FTP Storage Driver', 'ftp://ftp.server.com/public_html', $content);
};

// 7. Health
$generators['health'] = function (): string {
    $content = <<<SVG
    <rect x="0" y="0" width="770" height="395" fill="#0b1120"/>
    
    <!-- Top Overall Health Banner -->
    <rect x="20" y="15" width="730" height="60" rx="8" fill="#064e3b" stroke="#059669" stroke-width="1.5"/>
    <circle cx="50" cy="45" r="14" fill="#10b981"/>
    <text x="50" y="50" fill="#ffffff" font-size="15" font-weight="800" text-anchor="middle">✔</text>
    <text x="75" y="42" fill="#ffffff" font-size="15" font-weight="700">All Systems Operational</text>
    <text x="75" y="58" fill="#a7f3d0" font-size="11">Production Health Check • Status Code: 200 OK • Checked in 4.2ms</text>
    <text x="730" y="50" fill="#6ee7b7" font-size="11" text-anchor="end" font-family="monospace">2026-10-04 18:45:12</text>

    <!-- Cards Grid -->
    <!-- Card 1: Database -->
    <rect x="20" y="90" width="230" height="135" rx="6" fill="#1e293b" stroke="#334155" stroke-width="1"/>
    <circle cx="40" cy="115" r="6" fill="#10b981"/>
    <text x="55" y="119" fill="#f8fafc" font-size="13" font-weight="600">Database</text>
    <text x="235" y="119" fill="#34d399" font-size="11" text-anchor="end" font-weight="600">OK</text>
    <line x1="35" y1="135" x2="235" y2="135" stroke="#334155" stroke-width="0.5"/>
    <text x="35" y="155" fill="#94a3b8" font-size="10.5">Driver: MySQL 8.0</text>
    <text x="35" y="175" fill="#94a3b8" font-size="10.5">Ping Latency: 1.2ms</text>
    <text x="35" y="195" fill="#94a3b8" font-size="10.5">Connections: 4 active / 100 max</text>
    <text x="35" y="215" fill="#38bdf8" font-size="10" font-family="monospace">SELECT 1 (healthy)</text>

    <!-- Card 2: Redis Queue -->
    <rect x="270" y="90" width="230" height="135" rx="6" fill="#1e293b" stroke="#334155" stroke-width="1"/>
    <circle cx="290" cy="115" r="6" fill="#10b981"/>
    <text x="305" y="119" fill="#f8fafc" font-size="13" font-weight="600">Redis &amp; Queue</text>
    <text x="485" y="119" fill="#34d399" font-size="11" text-anchor="end" font-weight="600">OK</text>
    <line x1="285" y1="135" x2="485" y2="135" stroke="#334155" stroke-width="0.5"/>
    <text x="285" y="155" fill="#94a3b8" font-size="10.5">Driver: Redis (Socket)</text>
    <text x="285" y="175" fill="#94a3b8" font-size="10.5">Pending Jobs: 0</text>
    <text x="285" y="195" fill="#94a3b8" font-size="10.5">Failed Jobs: 0</text>
    <text x="285" y="215" fill="#38bdf8" font-size="10" font-family="monospace">QUEUE_DRIVER=redis</text>

    <!-- Card 3: Storage Disks -->
    <rect x="520" y="90" width="230" height="135" rx="6" fill="#1e293b" stroke="#334155" stroke-width="1"/>
    <circle cx="540" cy="115" r="6" fill="#10b981"/>
    <text x="555" y="119" fill="#f8fafc" font-size="13" font-weight="600">Storage Disks</text>
    <text x="735" y="119" fill="#34d399" font-size="11" text-anchor="end" font-weight="600">OK</text>
    <line x1="535" y1="135" x2="735" y2="135" stroke="#334155" stroke-width="0.5"/>
    <text x="535" y="155" fill="#94a3b8" font-size="10.5">Disks: local, public, s3</text>
    <text x="535" y="175" fill="#94a3b8" font-size="10.5">Local Writable: Yes</text>
    <text x="535" y="195" fill="#94a3b8" font-size="10.5">S3 Bucket: Connected</text>
    <text x="535" y="215" fill="#38bdf8" font-size="10" font-family="monospace">Permissions: 0755</text>

    <!-- Card 4: Disk Space -->
    <rect x="20" y="240" width="355" height="135" rx="6" fill="#1e293b" stroke="#334155" stroke-width="1"/>
    <circle cx="40" cy="265" r="6" fill="#10b981"/>
    <text x="55" y="269" fill="#f8fafc" font-size="13" font-weight="600">Disk Space Capacity</text>
    <text x="360" y="269" fill="#34d399" font-size="11" text-anchor="end" font-weight="600">142.6 GB Free (55%)</text>
    <rect x="35" y="285" width="325" height="10" rx="5" fill="#0f172a"/>
    <rect x="35" y="285" width="145" height="10" rx="5" fill="#3b82f6"/>
    <text x="35" y="315" fill="#94a3b8" font-size="11">Total: 256.0 GB • Used: 113.4 GB • Mount: /var/www</text>
    <text x="35" y="335" fill="#94a3b8" font-size="11">Threshold: Alert at &lt; 5.0 GB free</text>
    <text x="35" y="355" fill="#34d399" font-size="10.5" font-weight="600">✔ Healthy space available</text>

    <!-- Card 5: Memory & Uptime -->
    <rect x="395" y="240" width="355" height="135" rx="6" fill="#1e293b" stroke="#334155" stroke-width="1"/>
    <circle cx="415" cy="265" r="6" fill="#10b981"/>
    <text x="430" y="269" fill="#f8fafc" font-size="13" font-weight="600">Server Memory &amp; Uptime</text>
    <text x="735" y="269" fill="#34d399" font-size="11" text-anchor="end" font-weight="600">99.98% Uptime</text>
    <rect x="410" y="285" width="325" height="10" rx="5" fill="#0f172a"/>
    <rect x="410" y="285" width="35" height="10" rx="5" fill="#10b981"/>
    <text x="410" y="315" fill="#94a3b8" font-size="11">PHP Memory Peak: 14.8 MB / 512 MB Limit</text>
    <text x="410" y="335" fill="#94a3b8" font-size="11">PHP Version: 8.2.14 • Server: Nginx 1.24</text>
    <text x="410" y="355" fill="#34d399" font-size="10.5" font-weight="600">✔ Memory usage well within limits</text>
SVG;
    return wrapSvg('Health Monitoring', 'https://app.test/health', $content);
};

// 8. Image
$generators['image'] = function (): string {
    $content = <<<SVG
    <rect x="0" y="0" width="770" height="395" fill="#090d16"/>
    
    <!-- Top Code Header -->
    <rect x="20" y="15" width="730" height="40" rx="6" fill="#1e293b" stroke="#334155" stroke-width="1"/>
    <text x="35" y="39" fill="#38bdf8" font-size="11.5" font-family="monospace">Image::make(\$path)-&gt;fit(800, 800)-&gt;watermark(\$logo, 'bottom-right')-&gt;toWebp(85);</text>

    <!-- 4 Panels Showcase -->
    <!-- Panel 1: Original -->
    <rect x="20" y="68" width="170" height="230" rx="6" fill="#1e293b" stroke="#334155" stroke-width="1"/>
    <rect x="30" y="78" width="150" height="120" rx="4" fill="#334155"/>
    <circle cx="80" cy="120" r="22" fill="#f59e0b"/>
    <path d="M 40 180 L 80 135 L 120 180 Z" fill="#64748b"/>
    <path d="M 100 180 L 135 145 L 170 180 Z" fill="#475569"/>
    <text x="105" y="222" fill="#f8fafc" font-size="12" font-weight="700" text-anchor="middle">1. Original Photo</text>
    <text x="105" y="242" fill="#94a3b8" font-size="10.5" text-anchor="middle">4000 × 3000 px</text>
    <text x="105" y="260" fill="#f87171" font-size="11" font-weight="600" text-anchor="middle">4.2 MB (JPEG)</text>
    <text x="105" y="280" fill="#64748b" font-size="9.5" text-anchor="middle">Unoptimized raw upload</text>

    <!-- Panel 2: Smart Fit & Crop -->
    <rect x="205" y="68" width="170" height="230" rx="6" fill="#1e293b" stroke="#334155" stroke-width="1"/>
    <rect x="215" y="78" width="150" height="120" rx="4" fill="#1e3a8a"/>
    <circle cx="270" cy="120" r="20" fill="#38bdf8"/>
    <text x="290" y="170" fill="#ffffff" font-size="10" font-weight="600" text-anchor="middle">800 × 800</text>
    <text x="290" y="222" fill="#f8fafc" font-size="12" font-weight="700" text-anchor="middle">2. Smart Fit / Crop</text>
    <text x="290" y="242" fill="#94a3b8" font-size="10.5" text-anchor="middle">Center focus auto-crop</text>
    <text x="290" y="260" fill="#60a5fa" font-size="11" font-weight="600" text-anchor="middle">800 × 800 px</text>
    <text x="290" y="280" fill="#64748b" font-size="9.5" text-anchor="middle">Aspect ratio preserved</text>

    <!-- Panel 3: Watermark Overlay -->
    <rect x="390" y="68" width="170" height="230" rx="6" fill="#1e293b" stroke="#334155" stroke-width="1"/>
    <rect x="400" y="78" width="150" height="120" rx="4" fill="#312e81"/>
    <rect x="475" y="160" width="65" height="25" rx="3" fill="#ffffff" fill-opacity="0.8"/>
    <text x="507" y="177" fill="#1e1b4b" font-size="9" font-weight="800" text-anchor="middle">BONGO</text>
    <text x="475" y="222" fill="#f8fafc" font-size="12" font-weight="700" text-anchor="middle">3. Watermarking</text>
    <text x="475" y="242" fill="#94a3b8" font-size="10.5" text-anchor="middle">Alpha blend overlay</text>
    <text x="475" y="260" fill="#a78bfa" font-size="11" font-weight="600" text-anchor="middle">Bottom-Right / Opacity</text>
    <text x="475" y="280" fill="#64748b" font-size="9.5" text-anchor="middle">PNG alpha transparency</text>

    <!-- Panel 4: WebP Conversion -->
    <rect x="575" y="68" width="175" height="230" rx="6" fill="#064e3b" stroke="#059669" stroke-width="1.5"/>
    <rect x="585" y="78" width="155" height="120" rx="4" fill="#022c22"/>
    <text x="662" y="130" fill="#34d399" font-size="28" font-weight="800" text-anchor="middle">-97%</text>
    <text x="662" y="155" fill="#a7f3d0" font-size="11" text-anchor="middle">File Size Reduction</text>
    <text x="662" y="222" fill="#ffffff" font-size="12" font-weight="700" text-anchor="middle">4. WebP Converted</text>
    <text x="662" y="242" fill="#a7f3d0" font-size="10.5" text-anchor="middle">800 × 800 px</text>
    <text x="662" y="260" fill="#34d399" font-size="13" font-weight="800" text-anchor="middle">128 KB (WebP)</text>
    <text x="662" y="280" fill="#6ee7b7" font-size="9.5" text-anchor="middle">Crisp quality, instant load</text>

    <!-- Bottom Metrics -->
    <rect x="20" y="315" width="730" height="65" rx="6" fill="#1e293b" stroke="#334155" stroke-width="1"/>
    <text x="35" y="340" fill="#f8fafc" font-size="12" font-weight="600">Pure-PHP GD Engine • Zero Node / Imagick required</text>
    <text x="35" y="362" fill="#94a3b8" font-size="11">Operations: Resize, Scale, Crop, Fit, Watermark, Blur, Brightness, Greyscale, WebP, JPEG, PNG</text>
    <text x="730" y="352" fill="#34d399" font-size="12" font-weight="700" text-anchor="end">17 Tests Passing</text>
SVG;
    return wrapSvg('Pure-PHP Image Transformation', 'https://app.test/media/process', $content);
};

// 9. Nagad
$generators['nagad'] = function (): string {
    $content = <<<SVG
    <rect x="0" y="0" width="770" height="395" fill="#18181b"/>
    
    <rect x="185" y="25" width="400" height="345" rx="10" fill="#ffffff" filter="drop-shadow(0 10px 25px rgba(0,0,0,0.5))"/>
    
    <!-- Nagad Header Red/Orange -->
    <path d="M 185 35 A 10 10 0 0 1 195 25 L 575 25 A 10 10 0 0 1 585 35 L 585 90 L 185 90 Z" fill="#E41B23"/>
    <text x="385" y="60" fill="#ffffff" font-size="20" font-weight="800" text-anchor="middle">নগদ (Nagad)</text>
    <text x="385" y="78" fill="#fee2e2" font-size="11" text-anchor="middle">Direct MFS Payment Gateway</text>

    <!-- Order Info -->
    <rect x="205" y="105" width="360" height="52" rx="6" fill="#fef2f2" stroke="#fecaca" stroke-width="1"/>
    <text x="220" y="125" fill="#991b1b" font-size="11" font-weight="600">Merchant: Bongo Shop Ltd</text>
    <text x="220" y="143" fill="#64748b" font-size="10.5">Order ID: NAG-2026-9901</text>
    <text x="550" y="136" fill="#E41B23" font-size="16" font-weight="700" text-anchor="end">৳ 1,890.00</text>

    <!-- Nagad Account Input -->
    <text x="205" y="180" fill="#374151" font-size="12" font-weight="600">Enter Your Nagad Account Number</text>
    <rect x="205" y="190" width="360" height="40" rx="6" fill="#ffffff" stroke="#e5e7eb" stroke-width="1.5"/>
    <rect x="206" y="191" width="55" height="38" rx="5" fill="#f3f4f6"/>
    <text x="233" y="215" fill="#4b5563" font-size="12" font-weight="600" text-anchor="middle">+880</text>
    <text x="275" y="215" fill="#111827" font-size="13" font-family="monospace">1812 987654</text>

    <!-- Security Checkbox -->
    <rect x="205" y="242" width="16" height="16" rx="3" fill="#E41B23"/>
    <text x="213" y="254" fill="#ffffff" font-size="11" font-weight="700" text-anchor="middle">✔</text>
    <text x="230" y="255" fill="#4b5563" font-size="11">I agree to the Terms and Conditions of Nagad</text>

    <!-- Buttons -->
    <rect x="205" y="275" width="170" height="42" rx="6" fill="#e5e7eb"/>
    <text x="290" y="301" fill="#4b5563" font-size="13" font-weight="600" text-anchor="middle">CANCEL</text>

    <rect x="395" y="275" width="170" height="42" rx="6" fill="#E41B23"/>
    <text x="480" y="301" fill="#ffffff" font-size="13" font-weight="700" text-anchor="middle">PROCEED 🔒</text>

    <text x="385" y="345" fill="#9ca3af" font-size="10" text-anchor="middle">Encrypted via RSA-2048 &amp; SHA-256 Digital Signatures</text>
SVG;
    return wrapSvg('Nagad Direct Payment', 'https://api.mynagad.com/check-out/payment', $content);
};

// 10. PayPal
$generators['paypal'] = function (): string {
    $content = <<<SVG
    <rect x="0" y="0" width="770" height="395" fill="#f8fafc"/>
    
    <!-- Modal Container -->
    <rect x="185" y="25" width="400" height="345" rx="10" fill="#ffffff" stroke="#e2e8f0" stroke-width="1.5" filter="drop-shadow(0 10px 25px rgba(0,0,0,0.1))"/>
    
    <!-- PayPal Header -->
    <path d="M 185 35 A 10 10 0 0 1 195 25 L 575 25 A 10 10 0 0 1 585 35 L 585 80 L 185 80 Z" fill="#003087"/>
    <text x="385" y="58" fill="#ffffff" font-size="22" font-weight="800" text-anchor="middle" font-style="italic">PayPal</text>

    <!-- Order Summary -->
    <rect x="205" y="95" width="360" height="60" rx="6" fill="#f8fafc" stroke="#e2e8f0" stroke-width="1"/>
    <text x="220" y="118" fill="#334155" font-size="11.5" font-weight="600">Bongo Store — Order #ORD-8412</text>
    <text x="220" y="138" fill="#64748b" font-size="10.5">Subtotal: $45.00 • Tax: $3.60</text>
    <text x="550" y="132" fill="#0f172a" font-size="17" font-weight="800" text-anchor="end">$48.60 USD</text>

    <!-- Smart Buttons -->
    <rect x="205" y="175" width="360" height="44" rx="22" fill="#ffc439"/>
    <text x="385" y="202" fill="#003087" font-size="14" font-weight="800" font-style="italic" text-anchor="middle">Pay with PayPal</text>

    <rect x="205" y="230" width="360" height="44" rx="22" fill="#2c2e2f"/>
    <text x="385" y="257" fill="#ffffff" font-size="13" font-weight="600" text-anchor="middle">Debit or Credit Card</text>

    <!-- Webhook pill -->
    <rect x="225" y="295" width="320" height="26" rx="13" fill="#ecfdf5" stroke="#a7f3d0" stroke-width="1"/>
    <circle cx="240" cy="308" r="4" fill="#10b981"/>
    <text x="252" y="312" fill="#065f46" font-size="10" font-weight="600">Webhook Verified: PAYMENT.CAPTURE.COMPLETED</text>

    <text x="385" y="350" fill="#94a3b8" font-size="10" text-anchor="middle">PayPal REST API v2 • Orders, Captures, Refunds &amp; Webhooks</text>
SVG;
    return wrapSvg('PayPal Checkout', 'https://www.paypal.com/checkoutnow', $content);
};

// 11. Permission (RBAC)
$generators['permission'] = function (): string {
    $content = <<<SVG
    <rect x="0" y="0" width="770" height="395" fill="#0f172a"/>
    
    <!-- Header -->
    <rect x="20" y="15" width="730" height="42" rx="6" fill="#1e293b" stroke="#334155" stroke-width="1"/>
    <text x="35" y="41" fill="#f8fafc" font-size="13" font-weight="600">Role-Based Access Control (RBAC) Matrix</text>
    <rect x="630" y="24" width="105" height="24" rx="4" fill="#0284c7"/>
    <text x="682" y="40" fill="#ffffff" font-size="11" text-anchor="middle" font-weight="600">+ Create Role</text>

    <!-- Matrix Table Header -->
    <rect x="20" y="70" width="730" height="32" fill="#1e293b"/>
    <text x="35" y="91" fill="#94a3b8" font-size="10.5" font-weight="600">PERMISSION NAME</text>
    <text x="280" y="91" fill="#38bdf8" font-size="11" font-weight="700" text-anchor="middle">Super Admin</text>
    <text x="420" y="91" fill="#f8fafc" font-size="11" font-weight="600" text-anchor="middle">Store Manager</text>
    <text x="560" y="91" fill="#f8fafc" font-size="11" font-weight="600" text-anchor="middle">Editor</text>
    <text x="680" y="91" fill="#f8fafc" font-size="11" font-weight="600" text-anchor="middle">Customer</text>

    <!-- Row 1 -->
    <rect x="20" y="105" width="730" height="34" fill="#0b1120" stroke="#1e293b" stroke-width="0.5"/>
    <text x="35" y="127" fill="#f8fafc" font-size="11.5" font-family="monospace">manage-system</text>
    <text x="280" y="127" fill="#10b981" font-size="14" text-anchor="middle">✔</text>
    <text x="420" y="127" fill="#64748b" font-size="14" text-anchor="middle">—</text>
    <text x="560" y="127" fill="#64748b" font-size="14" text-anchor="middle">—</text>
    <text x="680" y="127" fill="#64748b" font-size="14" text-anchor="middle">—</text>

    <!-- Row 2 -->
    <rect x="20" y="142" width="730" height="34" fill="#0f172a" stroke="#1e293b" stroke-width="0.5"/>
    <text x="35" y="164" fill="#f8fafc" font-size="11.5" font-family="monospace">create-products</text>
    <text x="280" y="164" fill="#10b981" font-size="14" text-anchor="middle">✔</text>
    <text x="420" y="164" fill="#10b981" font-size="14" text-anchor="middle">✔</text>
    <text x="560" y="164" fill="#10b981" font-size="14" text-anchor="middle">✔</text>
    <text x="680" y="164" fill="#64748b" font-size="14" text-anchor="middle">—</text>

    <!-- Row 3 -->
    <rect x="20" y="179" width="730" height="34" fill="#0b1120" stroke="#1e293b" stroke-width="0.5"/>
    <text x="35" y="201" fill="#f8fafc" font-size="11.5" font-family="monospace">manage-orders</text>
    <text x="280" y="201" fill="#10b981" font-size="14" text-anchor="middle">✔</text>
    <text x="420" y="201" fill="#10b981" font-size="14" text-anchor="middle">✔</text>
    <text x="560" y="201" fill="#64748b" font-size="14" text-anchor="middle">—</text>
    <text x="680" y="201" fill="#64748b" font-size="14" text-anchor="middle">—</text>

    <!-- Row 4 -->
    <rect x="20" y="216" width="730" height="34" fill="#0f172a" stroke="#1e293b" stroke-width="0.5"/>
    <text x="35" y="238" fill="#f8fafc" font-size="11.5" font-family="monospace">issue-refunds</text>
    <text x="280" y="238" fill="#10b981" font-size="14" text-anchor="middle">✔</text>
    <text x="420" y="238" fill="#10b981" font-size="14" text-anchor="middle">✔</text>
    <text x="560" y="238" fill="#64748b" font-size="14" text-anchor="middle">—</text>
    <text x="680" y="238" fill="#64748b" font-size="14" text-anchor="middle">—</text>

    <!-- Row 5 -->
    <rect x="20" y="253" width="730" height="34" fill="#0b1120" stroke="#1e293b" stroke-width="0.5"/>
    <text x="35" y="275" fill="#f8fafc" font-size="11.5" font-family="monospace">checkout-cart</text>
    <text x="280" y="275" fill="#10b981" font-size="14" text-anchor="middle">✔</text>
    <text x="420" y="275" fill="#10b981" font-size="14" text-anchor="middle">✔</text>
    <text x="560" y="275" fill="#10b981" font-size="14" text-anchor="middle">✔</text>
    <text x="680" y="275" fill="#10b981" font-size="14" text-anchor="middle">✔</text>

    <!-- Code Snippet Footer -->
    <rect x="20" y="300" width="730" height="75" rx="6" fill="#1e293b" stroke="#334155" stroke-width="1"/>
    <text x="35" y="324" fill="#38bdf8" font-size="11.5" font-family="monospace">Route::get('/admin/orders', [OrderController::class, 'index'])-&gt;middleware('permission:manage-orders');</text>
    <text x="35" y="345" fill="#a7f3d0" font-size="11" font-family="monospace">if (\$user-&gt;can('issue-refunds')) { \$order-&gt;refund(); } // Trait HasRoles &amp; super-admin bypass</text>
    <text x="35" y="365" fill="#94a3b8" font-size="10">Middleware: RoleMiddleware ('role:admin') &amp; PermissionMiddleware ('permission:edit')</text>
SVG;
    return wrapSvg('Permission RBAC Matrix', 'https://app.test/admin/roles', $content);
};

// 12. S3
$generators['s3'] = function (): string {
    $content = <<<SVG
    <rect x="0" y="0" width="770" height="395" fill="#0f172a"/>
    
    <!-- Top Bar -->
    <rect x="20" y="15" width="730" height="42" rx="6" fill="#1e293b" stroke="#334155" stroke-width="1"/>
    <text x="35" y="41" fill="#f8fafc" font-size="13" font-weight="600">AWS S3 &amp; Cloudflare R2 Object Storage</text>
    <rect x="420" y="24" width="85" height="24" rx="4" fill="#334155"/>
    <text x="462" y="40" fill="#e2e8f0" font-size="11" text-anchor="middle">us-east-1</text>
    <rect x="515" y="24" width="125" height="24" rx="4" fill="#334155"/>
    <text x="577" y="40" fill="#38bdf8" font-size="11" text-anchor="middle" font-family="monospace">bongo-prod-bucket</text>
    <rect x="650" y="24" width="85" height="24" rx="4" fill="#0284c7"/>
    <text x="692" y="40" fill="#ffffff" font-size="11" text-anchor="middle" font-weight="600">+ Upload</text>

    <!-- Architecture & Features -->
    <rect x="20" y="70" width="350" height="200" rx="6" fill="#1e293b" stroke="#334155" stroke-width="1"/>
    <text x="35" y="95" fill="#f8fafc" font-size="12" font-weight="600">SigV4 Authorization Engine</text>
    <rect x="35" y="108" width="320" height="145" rx="4" fill="#0b1120"/>
    <text x="45" y="128" fill="#38bdf8" font-size="10.5" font-family="monospace">Authorization: AWS4-HMAC-SHA256</text>
    <text x="45" y="145" fill="#94a3b8" font-size="10" font-family="monospace">Credential=AKIA.../20261004/us-east-1/s3</text>
    <text x="45" y="162" fill="#94a3b8" font-size="10" font-family="monospace">SignedHeaders=host;x-amz-content-sha256</text>
    <text x="45" y="179" fill="#94a3b8" font-size="10" font-family="monospace">Signature=a8f4c2... (Zero external SDK)</text>
    <text x="45" y="202" fill="#34d399" font-size="10.5" font-weight="600">✔ Compatible: S3, Spaces, R2, MinIO</text>
    <text x="45" y="222" fill="#e2e8f0" font-size="10">Full multipart upload &amp; streaming download</text>

    <!-- Presigned URL Generator -->
    <rect x="390" y="70" width="360" height="200" rx="6" fill="#1e293b" stroke="#334155" stroke-width="1"/>
    <text x="405" y="95" fill="#f8fafc" font-size="12" font-weight="600">Presigned Temporary URLs</text>
    <rect x="405" y="108" width="330" height="145" rx="4" fill="#0b1120"/>
    <text x="415" y="128" fill="#38bdf8" font-size="10.5" font-family="monospace">Storage::disk('s3')-&gt;temporaryUrl(</text>
    <text x="430" y="145" fill="#e2e8f0" font-size="10.5" font-family="monospace">'reports/2026-q3.pdf',</text>
    <text x="430" y="162" fill="#f59e0b" font-size="10.5" font-family="monospace">now()-&gt;addMinutes(15)</text>
    <text x="415" y="179" fill="#38bdf8" font-size="10.5" font-family="monospace">);</text>
    <rect x="415" y="195" width="310" height="26" rx="4" fill="#065f46"/>
    <text x="570" y="212" fill="#34d399" font-size="10" text-anchor="middle" font-family="monospace">Expires in: 14m 58s • Single-use token</text>
    <text x="415" y="240" fill="#94a3b8" font-size="10">Safe private asset distribution without public buckets</text>

    <!-- File List Table -->
    <rect x="20" y="285" width="730" height="95" rx="6" fill="#1e293b" stroke="#334155" stroke-width="1"/>
    <text x="35" y="308" fill="#cbd5e1" font-size="11" font-weight="600">RECENT S3 OBJECTS</text>
    <text x="35" y="330" fill="#38bdf8" font-size="10.5" font-family="monospace">📄 avatars/user_102.webp (42 KB)</text>
    <text x="35" y="350" fill="#38bdf8" font-size="10.5" font-family="monospace">📄 backups/bongo_backup_2026-10-04.zip (41.2 MB)</text>
    <text x="35" y="370" fill="#38bdf8" font-size="10.5" font-family="monospace">📄 invoices/inv_8812.pdf (185 KB)</text>
    <text x="730" y="330" fill="#34d399" font-size="10" text-anchor="end">Public CDN: cdn.bongo.com/avatars/...</text>
    <text x="730" y="350" fill="#f59e0b" font-size="10" text-anchor="end">Private ACL: Restricted</text>
    <text x="730" y="370" fill="#34d399" font-size="10" text-anchor="end">Auto Cleanup on Download: Yes</text>
SVG;
    return wrapSvg('AWS S3 & Cloud Storage', 's3://bongo-prod-bucket/storage', $content);
};

// 13. SEO
$generators['seo'] = function (): string {
    $content = <<<SVG
    <rect x="0" y="0" width="770" height="395" fill="#f8fafc"/>
    
    <!-- Top Preview Header -->
    <rect x="20" y="15" width="730" height="42" rx="6" fill="#ffffff" stroke="#e2e8f0" stroke-width="1"/>
    <text x="35" y="41" fill="#0f172a" font-size="13" font-weight="700">Search Engine Optimization (SEO) &amp; Social Previews</text>
    <rect x="635" y="24" width="100" height="24" rx="4" fill="#0284c7"/>
    <text x="685" y="40" fill="#ffffff" font-size="10.5" font-weight="600" text-anchor="middle">View sitemap.xml</text>

    <!-- Google Search Snippet Preview -->
    <rect x="20" y="70" width="450" height="155" rx="6" fill="#ffffff" stroke="#e2e8f0" stroke-width="1"/>
    <text x="35" y="93" fill="#64748b" font-size="10.5" font-weight="700">GOOGLE SEARCH RESULT PREVIEW</text>
    <circle cx="43" cy="115" r="8" fill="#f1f5f9"/>
    <text x="43" y="119" fill="#0f172a" font-size="9" text-anchor="middle">B</text>
    <text x="58" y="114" fill="#202124" font-size="11">Bongo Store</text>
    <text x="58" y="128" fill="#4d5156" font-size="9.5">https://app.test › products › mechanical-keyboard</text>
    <text x="35" y="153" fill="#1a0dab" font-size="15" font-weight="600">Mechanical Gaming Keyboard RGB — Bongo Store</text>
    <text x="35" y="175" fill="#4d5156" font-size="11">Buy the best Mechanical Gaming Keyboard with hot-swappable RGB switches in Bangladesh. Fast delivery across Dhaka and nationwide.</text>
    <text x="35" y="198" fill="#10b981" font-size="10" font-weight="600">৳ 4,500.00 • In stock • 4.9 ★★★★★ (128 reviews)</text>

    <!-- Facebook / OpenGraph Preview -->
    <rect x="490" y="70" width="260" height="205" rx="6" fill="#ffffff" stroke="#e2e8f0" stroke-width="1"/>
    <text x="505" y="93" fill="#1877f2" font-size="10.5" font-weight="700">OPENGRAPH FACEBOOK CARD</text>
    <rect x="505" y="105" width="230" height="95" rx="4" fill="#cbd5e1"/>
    <text x="620" y="157" fill="#475569" font-size="11" text-anchor="middle">🖼 1200 × 630 OG Image</text>
    <rect x="505" y="200" width="230" height="65" fill="#f8fafc"/>
    <text x="515" y="218" fill="#64748b" font-size="9">APP.TEST</text>
    <text x="515" y="235" fill="#0f172a" font-size="11" font-weight="700">Mechanical Gaming Keyboard RGB</text>
    <text x="515" y="252" fill="#64748b" font-size="9.5">Official Bongo E-Commerce Store</text>

    <!-- Schema.org JSON-LD Banner -->
    <rect x="20" y="240" width="450" height="140" rx="6" fill="#0f172a" stroke="#1e293b" stroke-width="1"/>
    <text x="35" y="265" fill="#38bdf8" font-size="11" font-family="monospace">&lt;script type="application/ld+json"&gt;</text>
    <text x="35" y="285" fill="#f8fafc" font-size="10.5" font-family="monospace">{"@context": "https://schema.org/", "@type": "Product",</text>
    <text x="35" y="303" fill="#e2e8f0" font-size="10.5" font-family="monospace"> "name": "Mechanical Keyboard", "offers": {</text>
    <text x="35" y="321" fill="#34d399" font-size="10.5" font-family="monospace">   "@type": "Offer", "price": "4500", "priceCurrency": "BDT"</text>
    <text x="35" y="339" fill="#e2e8f0" font-size="10.5" font-family="monospace"> }}</text>
    <text x="35" y="358" fill="#38bdf8" font-size="11" font-family="monospace">&lt;/script&gt; // Auto-generated with SEO::schema()</text>

    <!-- XML Sitemap Box -->
    <rect x="490" y="290" width="260" height="90" rx="6" fill="#0f172a" stroke="#1e293b" stroke-width="1"/>
    <text x="505" y="313" fill="#38bdf8" font-size="11" font-weight="600">XML SITEMAP GENERATOR</text>
    <text x="505" y="333" fill="#94a3b8" font-size="10.5" font-family="monospace">php bongo sitemap:generate</text>
    <text x="505" y="353" fill="#34d399" font-size="10">✔ Generated public/sitemap.xml (48 URLs)</text>
    <text x="505" y="370" fill="#cbd5e1" font-size="9.5">Robots.txt integration &amp; Ping Google</text>
SVG;
    return wrapSvg('SEO & Social Preview Cards', 'https://app.test/products/keyboard', $content);
};

// 14. Settings
$generators['settings'] = function (): string {
    $content = <<<SVG
    <rect x="0" y="0" width="770" height="395" fill="#0f172a"/>
    
    <!-- Header -->
    <rect x="20" y="15" width="730" height="42" rx="6" fill="#1e293b" stroke="#334155" stroke-width="1"/>
    <text x="35" y="41" fill="#f8fafc" font-size="13" font-weight="600">Dynamic Database Application Settings</text>
    <rect x="635" y="24" width="100" height="24" rx="4" fill="#0284c7"/>
    <text x="685" y="40" fill="#ffffff" font-size="11" text-anchor="middle" font-weight="600">Save Changes</text>

    <!-- Sidebar Categories -->
    <rect x="20" y="70" width="180" height="310" rx="6" fill="#1e293b" stroke="#334155" stroke-width="1"/>
    <rect x="20" y="70" width="180" height="36" rx="6" fill="#0284c7"/>
    <text x="40" y="93" fill="#ffffff" font-size="12" font-weight="700">General Settings</text>

    <text x="40" y="130" fill="#94a3b8" font-size="12">Store &amp; Currency</text>
    <text x="40" y="165" fill="#94a3b8" font-size="12">Payment Gateways</text>
    <text x="40" y="200" fill="#94a3b8" font-size="12">Mail SMTP</text>
    <text x="40" y="235" fill="#94a3b8" font-size="12">SMS Gateways</text>
    <text x="40" y="270" fill="#94a3b8" font-size="12">Security &amp; 2FA</text>
    <text x="40" y="305" fill="#94a3b8" font-size="12">Maintenance Mode</text>

    <!-- Settings Fields Table -->
    <rect x="215" y="70" width="535" height="310" rx="6" fill="#1e293b" stroke="#334155" stroke-width="1"/>
    
    <!-- Setting 1: site_title -->
    <text x="235" y="100" fill="#f8fafc" font-size="12" font-weight="600">site_title</text>
    <rect x="300" y="87" width="50" height="18" rx="3" fill="#334155"/>
    <text x="325" y="100" fill="#38bdf8" font-size="10" text-anchor="middle">string</text>
    <rect x="365" y="85" width="365" height="26" rx="4" fill="#0f172a" stroke="#334155" stroke-width="1"/>
    <text x="375" y="102" fill="#ffffff" font-size="11">Bongo Official E-Commerce Store</text>

    <!-- Setting 2: store_currency -->
    <text x="235" y="145" fill="#f8fafc" font-size="12" font-weight="600">currency</text>
    <rect x="300" y="132" width="50" height="18" rx="3" fill="#334155"/>
    <text x="325" y="145" fill="#38bdf8" font-size="10" text-anchor="middle">string</text>
    <rect x="365" y="130" width="365" height="26" rx="4" fill="#0f172a" stroke="#334155" stroke-width="1"/>
    <text x="375" y="147" fill="#ffffff" font-size="11">BDT (৳)</text>

    <!-- Setting 3: tax_rate -->
    <text x="235" y="190" fill="#f8fafc" font-size="12" font-weight="600">tax_rate</text>
    <rect x="300" y="177" width="50" height="18" rx="3" fill="#334155"/>
    <text x="325" y="190" fill="#a78bfa" font-size="10" text-anchor="middle">float</text>
    <rect x="365" y="175" width="365" height="26" rx="4" fill="#0f172a" stroke="#334155" stroke-width="1"/>
    <text x="375" y="192" fill="#ffffff" font-size="11">7.5</text>

    <!-- Setting 4: enable_registration -->
    <text x="235" y="235" fill="#f8fafc" font-size="12" font-weight="600">user_signup</text>
    <rect x="300" y="222" width="50" height="18" rx="3" fill="#334155"/>
    <text x="325" y="235" fill="#34d399" font-size="10" text-anchor="middle">bool</text>
    <rect x="365" y="224" width="44" height="22" rx="11" fill="#10b981"/>
    <circle cx="397" cy="235" r="8" fill="#ffffff"/>
    <text x="420" y="240" fill="#34d399" font-size="11" font-weight="600">Enabled (true)</text>

    <!-- Setting 5: social_links -->
    <text x="235" y="280" fill="#f8fafc" font-size="12" font-weight="600">social_urls</text>
    <rect x="300" y="267" width="50" height="18" rx="3" fill="#334155"/>
    <text x="325" y="280" fill="#f59e0b" font-size="10" text-anchor="middle">json</text>
    <rect x="365" y="265" width="365" height="45" rx="4" fill="#0f172a" stroke="#334155" stroke-width="1"/>
    <text x="375" y="285" fill="#38bdf8" font-size="10.5" font-family="monospace">{"facebook": "facebook.com/bongo",</text>
    <text x="385" y="300" fill="#38bdf8" font-size="10.5" font-family="monospace"> "twitter": "x.com/bongophp"}</text>

    <!-- Template helper banner -->
    <rect x="235" y="325" width="495" height="40" rx="4" fill="#0b1120"/>
    <text x="250" y="348" fill="#38bdf8" font-size="11" font-family="monospace">setting('site_title') • @setting('currency') • Type Casting Active</text>
SVG;
    return wrapSvg('Settings Dashboard', 'https://app.test/admin/settings', $content);
};

// 15. SMS
$generators['sms'] = function (): string {
    $content = <<<SVG
    <rect x="0" y="0" width="770" height="395" fill="#0f172a"/>
    
    <!-- Top Header -->
    <rect x="20" y="15" width="730" height="42" rx="6" fill="#1e293b" stroke="#334155" stroke-width="1"/>
    <text x="35" y="41" fill="#f8fafc" font-size="13" font-weight="600">Bangladeshi SMS Gateways &amp; Phone OTP Manager</text>
    <rect x="620" y="24" width="115" height="24" rx="4" fill="#0284c7"/>
    <text x="677" y="40" fill="#ffffff" font-size="11" text-anchor="middle" font-weight="600">+ Send Quick SMS</text>

    <!-- Left: Gateway Status Table -->
    <rect x="20" y="70" width="460" height="305" rx="6" fill="#1e293b" stroke="#334155" stroke-width="1"/>
    <rect x="20" y="70" width="460" height="32" rx="6" fill="#334155"/>
    <text x="35" y="91" fill="#f8fafc" font-size="11" font-weight="700">GATEWAY DRIVERS &amp; DISPATCH LOG</text>

    <!-- Driver Cards -->
    <rect x="35" y="115" width="130" height="65" rx="4" fill="#0f172a" stroke="#10b981" stroke-width="1"/>
    <text x="45" y="135" fill="#10b981" font-size="11.5" font-weight="700">BulkSMSBD</text>
    <text x="45" y="152" fill="#94a3b8" font-size="10">Balance: ৳ 4,120</text>
    <text x="45" y="168" fill="#34d399" font-size="9.5">Default Driver</text>

    <rect x="180" y="115" width="135" height="65" rx="4" fill="#0f172a" stroke="#334155" stroke-width="1"/>
    <text x="190" y="135" fill="#38bdf8" font-size="11.5" font-weight="700">SSL Wireless</text>
    <text x="190" y="152" fill="#94a3b8" font-size="10">Balance: 12,450 SMS</text>
    <text x="190" y="168" fill="#94a3b8" font-size="9.5">Active Driver</text>

    <rect x="330" y="115" width="135" height="65" rx="4" fill="#0f172a" stroke="#334155" stroke-width="1"/>
    <text x="340" y="135" fill="#f59e0b" font-size="11.5" font-weight="700">Alpha SMS</text>
    <text x="340" y="152" fill="#94a3b8" font-size="10">Balance: 8,920 SMS</text>
    <text x="340" y="168" fill="#94a3b8" font-size="9.5">Active Driver</text>

    <!-- Recent Logs -->
    <text x="35" y="205" fill="#94a3b8" font-size="10.5" font-weight="600">RECENT DISPATCH LOGS</text>
    
    <rect x="35" y="215" width="430" height="34" rx="4" fill="#0b1120"/>
    <text x="45" y="236" fill="#34d399" font-size="10" font-weight="700">SENT</text>
    <text x="85" y="236" fill="#cbd5e1" font-size="11" font-family="monospace">01712345678</text>
    <text x="210" y="236" fill="#94a3b8" font-size="10.5">Your OTP is 482910</text>
    <text x="450" y="236" fill="#64748b" font-size="10" text-anchor="end">12s ago</text>

    <rect x="35" y="255" width="430" height="34" rx="4" fill="#0b1120"/>
    <text x="45" y="276" fill="#34d399" font-size="10" font-weight="700">SENT</text>
    <text x="85" y="276" fill="#cbd5e1" font-size="11" font-family="monospace">01898765432</text>
    <text x="210" y="276" fill="#94a3b8" font-size="10.5">Order #1092 Confirmed</text>
    <text x="450" y="276" fill="#64748b" font-size="10" text-anchor="end">2m ago</text>

    <text x="35" y="325" fill="#38bdf8" font-size="10.5" font-family="monospace">SMS::to('017XXXXXXXX')-&gt;otp()-&gt;send(); // Auto 6-digit OTP</text>
    <text x="35" y="345" fill="#a7f3d0" font-size="10.5" font-family="monospace">SMS::verifyOtp('017XXXXXXXX', \$code); // 5-min expiration</text>

    <!-- Right: Mobile Screen Mockup -->
    <rect x="505" y="70" width="245" height="305" rx="16" fill="#020617" stroke="#334155" stroke-width="2"/>
    <rect x="585" y="78" width="85" height="12" rx="6" fill="#1e293b"/>
    <text x="627" y="115" fill="#94a3b8" font-size="10" text-anchor="middle">Messages • Today 18:45</text>

    <!-- SMS Bubble -->
    <rect x="520" y="130" width="215" height="85" rx="10" fill="#1e293b"/>
    <text x="532" y="152" fill="#38bdf8" font-size="11" font-weight="700">BONGO-OTP</text>
    <text x="532" y="172" fill="#f8fafc" font-size="11">Your Bongo verification code</text>
    <text x="532" y="190" fill="#f8fafc" font-size="11">is <tspan fill="#34d399" font-weight="800">482910</tspan>. Valid for 5 mins.</text>
    <text x="532" y="205" fill="#64748b" font-size="9.5">Do not share this code.</text>

    <!-- Verification Pad Mockup -->
    <rect x="525" y="235" width="205" height="125" rx="6" fill="#0f172a" stroke="#1e293b" stroke-width="1"/>
    <text x="627" y="258" fill="#e2e8f0" font-size="11" font-weight="600" text-anchor="middle">Enter OTP Code</text>
    <rect x="545" y="270" width="25" height="32" rx="4" fill="#1e293b" stroke="#34d399" stroke-width="1.5"/>
    <text x="557" y="291" fill="#ffffff" font-size="13" font-weight="700" text-anchor="middle">4</text>
    <rect x="575" y="270" width="25" height="32" rx="4" fill="#1e293b" stroke="#34d399" stroke-width="1.5"/>
    <text x="587" y="291" fill="#ffffff" font-size="13" font-weight="700" text-anchor="middle">8</text>
    <rect x="605" y="270" width="25" height="32" rx="4" fill="#1e293b" stroke="#34d399" stroke-width="1.5"/>
    <text x="617" y="291" fill="#ffffff" font-size="13" font-weight="700" text-anchor="middle">2</text>
    <rect x="635" y="270" width="25" height="32" rx="4" fill="#1e293b" stroke="#34d399" stroke-width="1.5"/>
    <text x="647" y="291" fill="#ffffff" font-size="13" font-weight="700" text-anchor="middle">9</text>
    <rect x="665" y="270" width="25" height="32" rx="4" fill="#1e293b" stroke="#34d399" stroke-width="1.5"/>
    <text x="677" y="291" fill="#ffffff" font-size="13" font-weight="700" text-anchor="middle">1</text>
    <rect x="695" y="270" width="25" height="32" rx="4" fill="#1e293b" stroke="#34d399" stroke-width="1.5"/>
    <text x="707" y="291" fill="#ffffff" font-size="13" font-weight="700" text-anchor="middle">0</text>
    <text x="627" y="325" fill="#34d399" font-size="10.5" font-weight="600" text-anchor="middle">✔ Code Verified</text>
SVG;
    return wrapSvg('SMS Gateway & Phone OTP', 'https://app.test/admin/sms', $content);
};

// 16. Social Login
$generators['social-login'] = function (): string {
    $content = <<<SVG
    <rect x="0" y="0" width="770" height="395" fill="#0f172a"/>
    
    <!-- Login Card -->
    <rect x="220" y="30" width="330" height="335" rx="10" fill="#1e293b" stroke="#334155" stroke-width="1.5" filter="drop-shadow(0 10px 25px rgba(0,0,0,0.4))"/>
    
    <text x="385" y="65" fill="#ffffff" font-size="16" font-weight="700" text-anchor="middle">Sign In to Bongo</text>
    <text x="385" y="85" fill="#94a3b8" font-size="11.5" text-anchor="middle">Zero-dependency OAuth 2.0 Authentication</text>

    <!-- Google Button -->
    <rect x="250" y="110" width="270" height="42" rx="6" fill="#ffffff"/>
    <circle cx="275" cy="131" r="8" fill="#ea4335"/>
    <text x="390" y="136" fill="#1e293b" font-size="12.5" font-weight="600" text-anchor="middle">Continue with Google</text>

    <!-- GitHub Button -->
    <rect x="250" y="165" width="270" height="42" rx="6" fill="#0f172a" stroke="#334155" stroke-width="1"/>
    <circle cx="275" cy="186" r="8" fill="#ffffff"/>
    <text x="390" y="191" fill="#ffffff" font-size="12.5" font-weight="600" text-anchor="middle">Continue with GitHub</text>

    <!-- Facebook Button -->
    <rect x="250" y="220" width="270" height="42" rx="6" fill="#1877f2"/>
    <circle cx="275" cy="241" r="8" fill="#ffffff"/>
    <text x="390" y="246" fill="#ffffff" font-size="12.5" font-weight="600" text-anchor="middle">Continue with Facebook</text>

    <text x="385" y="285" fill="#64748b" font-size="10.5" text-anchor="middle">CSRF State Token Validation &amp; Private Email Resolution</text>

    <!-- Profile badge preview -->
    <rect x="250" y="300" width="270" height="50" rx="6" fill="#0b1120" stroke="#334155" stroke-width="1"/>
    <circle cx="275" cy="325" r="14" fill="#38bdf8"/>
    <text x="275" y="330" fill="#0f172a" font-size="11" font-weight="700" text-anchor="middle">RA</text>
    <text x="300" y="321" fill="#f8fafc" font-size="11" font-weight="600">Rahim Ahmed</text>
    <text x="300" y="337" fill="#34d399" font-size="9.5">rahim@gmail.com • Google Verified</text>
SVG;
    return wrapSvg('Social Authentication', 'https://app.test/login', $content);
};

// 17. SSLCommerz
$generators['sslcommerz'] = function (): string {
    $content = <<<SVG
    <rect x="0" y="0" width="770" height="395" fill="#f1f5f9"/>
    
    <!-- Modal -->
    <rect x="155" y="25" width="460" height="345" rx="10" fill="#ffffff" stroke="#cbd5e1" stroke-width="1" filter="drop-shadow(0 10px 25px rgba(0,0,0,0.15))"/>
    
    <!-- Header -->
    <rect x="155" y="25" width="460" height="60" rx="10" fill="#0f172a"/>
    <text x="180" y="55" fill="#ffffff" font-size="18" font-weight="800">SSLCOMMERZ</text>
    <text x="180" y="72" fill="#38bdf8" font-size="10">Bangladesh's Largest Payment Gateway</text>
    <text x="590" y="55" fill="#ffffff" font-size="15" font-weight="800" text-anchor="end">৳ 5,400.00 BDT</text>
    <text x="590" y="72" fill="#94a3b8" font-size="10" text-anchor="end">Invoice: BNG-10492</text>

    <!-- Channels Tabs -->
    <rect x="175" y="100" width="130" height="30" rx="4" fill="#0284c7"/>
    <text x="240" y="119" fill="#ffffff" font-size="11" font-weight="700" text-anchor="middle">Cards (Visa / MC)</text>

    <rect x="315" y="100" width="140" height="30" rx="4" fill="#f1f5f9"/>
    <text x="385" y="119" fill="#475569" font-size="11" font-weight="600" text-anchor="middle">Mobile Banking</text>

    <rect x="465" y="100" width="130" height="30" rx="4" fill="#f1f5f9"/>
    <text x="530" y="119" fill="#475569" font-size="11" font-weight="600" text-anchor="middle">Net Banking</text>

    <!-- Cards selection grid -->
    <rect x="175" y="145" width="95" height="55" rx="4" fill="#ffffff" stroke="#0284c7" stroke-width="2"/>
    <text x="222" y="177" fill="#1e3a8a" font-size="15" font-weight="800" text-anchor="middle">VISA</text>

    <rect x="285" y="145" width="95" height="55" rx="4" fill="#ffffff" stroke="#cbd5e1" stroke-width="1"/>
    <circle cx="325" cy="172" r="12" fill="#ea580c"/>
    <circle cx="340" cy="172" r="12" fill="#eab308" fill-opacity="0.8"/>

    <rect x="395" y="145" width="95" height="55" rx="4" fill="#ffffff" stroke="#cbd5e1" stroke-width="1"/>
    <text x="442" y="177" fill="#0284c7" font-size="13" font-weight="800" text-anchor="middle">AMEX</text>

    <rect x="505" y="145" width="95" height="55" rx="4" fill="#ffffff" stroke="#cbd5e1" stroke-width="1"/>
    <text x="552" y="177" fill="#059669" font-size="12" font-weight="700" text-anchor="middle">UnionPay</text>

    <!-- Mobile banking preview -->
    <text x="175" y="225" fill="#475569" font-size="11" font-weight="600">Also supports: bKash, Nagad, Rocket, Upay, Cellfin</text>

    <!-- Pay button -->
    <rect x="175" y="245" width="425" height="42" rx="6" fill="#0284c7"/>
    <text x="387" y="271" fill="#ffffff" font-size="13" font-weight="700" text-anchor="middle">PAY ৳ 5,400.00 BDT NOW 🔒</text>

    <!-- Security & PCI DSS -->
    <rect x="175" y="305" width="425" height="50" rx="4" fill="#f8fafc" stroke="#e2e8f0" stroke-width="1"/>
    <text x="387" y="325" fill="#10b981" font-size="10.5" font-weight="700" text-anchor="middle">✔ 100% PCI-DSS Level 1 Certified • 256-Bit TLS Encryption</text>
    <text x="387" y="342" fill="#64748b" font-size="10" text-anchor="middle">IPN Webhook Auto-Verification &amp; Hash Validation</text>
SVG;
    return wrapSvg('SSLCommerz Gateway', 'https://securepay.sslcommerz.com/gwprocess/v4', $content);
};

// 18. Stripe
$generators['stripe'] = function (): string {
    $content = <<<SVG
    <rect x="0" y="0" width="770" height="395" fill="#f8fafc"/>
    
    <!-- Stripe Checkout Modal -->
    <rect x="160" y="25" width="450" height="345" rx="10" fill="#ffffff" stroke="#e2e8f0" stroke-width="1" filter="drop-shadow(0 10px 25px rgba(0,0,0,0.1))"/>
    
    <!-- Header -->
    <path d="M 160 35 A 10 10 0 0 1 170 25 L 600 25 A 10 10 0 0 1 610 35 L 610 85 L 160 85 Z" fill="#635BFF"/>
    <text x="385" y="58" fill="#ffffff" font-size="20" font-weight="800" text-anchor="middle">stripe</text>
    <text x="385" y="75" fill="#e0e7ff" font-size="10.5" text-anchor="middle">International Card Payments &amp; Checkout Sessions</text>

    <!-- Order summary -->
    <rect x="185" y="100" width="400" height="50" rx="6" fill="#f8fafc" stroke="#e2e8f0" stroke-width="1"/>
    <text x="200" y="122" fill="#1e293b" font-size="11.5" font-weight="600">Bongo Pro Developer License</text>
    <text x="200" y="138" fill="#64748b" font-size="10.5">Billed monthly • Cancel anytime</text>
    <text x="565" y="131" fill="#0f172a" font-size="16" font-weight="800" text-anchor="end">$29.00 USD</text>

    <!-- Card Input Mockup -->
    <text x="185" y="172" fill="#374151" font-size="11.5" font-weight="600">Card information</text>
    <rect x="185" y="180" width="400" height="38" rx="6" fill="#ffffff" stroke="#635BFF" stroke-width="1.5"/>
    <text x="200" y="204" fill="#0f172a" font-size="12" font-family="monospace">4242 •••• •••• 4242</text>
    <text x="460" y="204" fill="#64748b" font-size="12" font-family="monospace">12/28</text>
    <text x="535" y="204" fill="#64748b" font-size="12" font-family="monospace">123</text>

    <!-- Pay Button -->
    <rect x="185" y="235" width="400" height="42" rx="6" fill="#635BFF"/>
    <text x="385" y="261" fill="#ffffff" font-size="13" font-weight="700" text-anchor="middle">Pay $29.00 with Stripe 🔒</text>

    <!-- Webhook pill -->
    <rect x="185" y="295" width="400" height="55" rx="6" fill="#f8fafc" stroke="#e2e8f0" stroke-width="1"/>
    <circle cx="205" cy="318" r="4" fill="#10b981"/>
    <text x="218" y="322" fill="#166534" font-size="10.5" font-weight="700">Webhook Verified: checkout.session.completed</text>
    <text x="200" y="340" fill="#64748b" font-size="10">HMAC-SHA256 signature verification &amp; 300s replay attack defense</text>
SVG;
    return wrapSvg('Stripe Checkout', 'https://checkout.stripe.com/pay', $content);
};

// 19. Two-Factor
$generators['two-factor'] = function (): string {
    $content = <<<SVG
    <rect x="0" y="0" width="770" height="395" fill="#0f172a"/>
    
    <!-- 2FA Card -->
    <rect x="60" y="25" width="650" height="345" rx="8" fill="#1e293b" stroke="#334155" stroke-width="1.5"/>
    <text x="85" y="55" fill="#ffffff" font-size="15" font-weight="700">Two-Factor Authentication (2FA)</text>
    <text x="85" y="73" fill="#94a3b8" font-size="11">RFC 6238 TOTP with Google Authenticator, Authy, and 1Password</text>

    <!-- Left: QR Code Preview -->
    <rect x="85" y="95" width="160" height="160" rx="8" fill="#ffffff"/>
    <!-- Mock QR Matrix -->
    <rect x="100" y="110" width="30" height="30" fill="#0f172a"/>
    <rect x="107" y="117" width="16" height="16" fill="#ffffff"/>
    <rect x="200" y="110" width="30" height="30" fill="#0f172a"/>
    <rect x="207" y="117" width="16" height="16" fill="#ffffff"/>
    <rect x="100" y="210" width="30" height="30" fill="#0f172a"/>
    <rect x="107" y="217" width="16" height="16" fill="#ffffff"/>
    <circle cx="165" cy="175" r="16" fill="#0284c7"/>
    <text x="165" y="180" fill="#ffffff" font-size="13" font-weight="800" text-anchor="middle">🛡</text>

    <text x="165" y="275" fill="#94a3b8" font-size="10" text-anchor="middle">Scan with Authenticator App</text>
    <rect x="85" y="290" width="160" height="24" rx="4" fill="#0f172a"/>
    <text x="165" y="306" fill="#38bdf8" font-size="10.5" font-family="monospace" text-anchor="middle">JBSWY3DPEHPK3PXP</text>

    <!-- Middle: Verification Input -->
    <text x="275" y="115" fill="#f8fafc" font-size="12" font-weight="600">Verification Code</text>
    <text x="275" y="132" fill="#94a3b8" font-size="10.5">Enter the 6-digit code from your app</text>
    
    <rect x="275" y="145" width="30" height="38" rx="4" fill="#0f172a" stroke="#10b981" stroke-width="1.5"/>
    <text x="290" y="169" fill="#ffffff" font-size="15" font-weight="700" text-anchor="middle">4</text>
    <rect x="312" y="145" width="30" height="38" rx="4" fill="#0f172a" stroke="#10b981" stroke-width="1.5"/>
    <text x="327" y="169" fill="#ffffff" font-size="15" font-weight="700" text-anchor="middle">8</text>
    <rect x="349" y="145" width="30" height="38" rx="4" fill="#0f172a" stroke="#10b981" stroke-width="1.5"/>
    <text x="364" y="169" fill="#ffffff" font-size="15" font-weight="700" text-anchor="middle">2</text>
    <rect x="386" y="145" width="30" height="38" rx="4" fill="#0f172a" stroke="#10b981" stroke-width="1.5"/>
    <text x="401" y="169" fill="#ffffff" font-size="15" font-weight="700" text-anchor="middle">9</text>
    <rect x="423" y="145" width="30" height="38" rx="4" fill="#0f172a" stroke="#10b981" stroke-width="1.5"/>
    <text x="438" y="169" fill="#ffffff" font-size="15" font-weight="700" text-anchor="middle">1</text>
    <rect x="460" y="145" width="30" height="38" rx="4" fill="#0f172a" stroke="#10b981" stroke-width="1.5"/>
    <text x="475" y="169" fill="#ffffff" font-size="15" font-weight="700" text-anchor="middle">0</text>

    <rect x="275" y="195" width="215" height="34" rx="4" fill="#10b981"/>
    <text x="382" y="217" fill="#ffffff" font-size="12" font-weight="700" text-anchor="middle">CONFIRM &amp; ACTIVATE</text>

    <!-- Right: Single-use Recovery Codes -->
    <rect x="515" y="95" width="180" height="225" rx="6" fill="#0b1120" stroke="#334155" stroke-width="1"/>
    <text x="530" y="118" fill="#f8fafc" font-size="11" font-weight="700">RECOVERY CODES</text>
    <text x="530" y="133" fill="#64748b" font-size="9.5">Save these in a safe place:</text>

    <text x="530" y="157" fill="#38bdf8" font-size="11" font-family="monospace">9a8b-7c6d</text>
    <text x="530" y="177" fill="#38bdf8" font-size="11" font-family="monospace">e5f4-3a2b</text>
    <text x="530" y="197" fill="#38bdf8" font-size="11" font-family="monospace">1c2d-3e4f</text>
    <text x="530" y="217" fill="#38bdf8" font-size="11" font-family="monospace">5a6b-7c8d</text>
    <text x="530" y="237" fill="#38bdf8" font-size="11" font-family="monospace">9e0f-1a2b</text>
    <text x="530" y="257" fill="#38bdf8" font-size="11" font-family="monospace">3c4d-5e6f</text>
    <text x="530" y="277" fill="#38bdf8" font-size="11" font-family="monospace">7a8b-9c0d</text>
    <text x="530" y="297" fill="#38bdf8" font-size="11" font-family="monospace">1e2f-3a4b</text>

    <text x="85" y="347" fill="#34d399" font-size="10.5">✔ Pure-PHP SVG QR Generation • Zero Google Charts / Third-Party Server Dependency</text>
SVG;
    return wrapSvg('Two-Factor Authentication Setup', 'https://app.test/2fa/setup', $content);
};

// 20. Zip
$generators['zip'] = function (): string {
    $content = <<<SVG
    <rect x="0" y="0" width="770" height="395" fill="#090d16"/>
    
    <!-- Terminal Header -->
    <rect x="20" y="15" width="730" height="365" rx="6" fill="#020617" stroke="#1e293b" stroke-width="1"/>
    
    <text x="40" y="48" fill="#38bdf8" font-size="13" font-weight="600" font-family="monospace">php &gt;</text>
    <text x="90" y="48" fill="#f8fafc" font-size="12.5" font-family="monospace">Zip::create('archive.zip')-&gt;addDirectory('storage/app')-&gt;save();</text>

    <!-- Inspection Table -->
    <text x="40" y="80" fill="#94a3b8" font-size="11.5" font-family="monospace">Archive: storage/backups/archive.zip (Total entries: 42)</text>

    <rect x="40" y="95" width="690" height="28" fill="#1e293b"/>
    <text x="55" y="114" fill="#94a3b8" font-size="10.5" font-weight="600">ENTRY NAME</text>
    <text x="350" y="114" fill="#94a3b8" font-size="10.5" font-weight="600">ORIGINAL</text>
    <text x="450" y="114" fill="#94a3b8" font-size="10.5" font-weight="600">COMPRESSED</text>
    <text x="560" y="114" fill="#94a3b8" font-size="10.5" font-weight="600">RATIO</text>
    <text x="650" y="114" fill="#94a3b8" font-size="10.5" font-weight="600">CRC32</text>

    <rect x="40" y="125" width="690" height="30" fill="#0f172a" stroke="#1e293b" stroke-width="0.5"/>
    <text x="55" y="145" fill="#38bdf8" font-size="11" font-family="monospace">database_backup.sql</text>
    <text x="350" y="145" fill="#cbd5e1" font-size="11" font-family="monospace">14.8 MB</text>
    <text x="450" y="145" fill="#cbd5e1" font-size="11" font-family="monospace">3.2 MB</text>
    <text x="560" y="145" fill="#34d399" font-size="11" font-weight="700">78.4%</text>
    <text x="650" y="145" fill="#94a3b8" font-size="11" font-family="monospace">a8f4c201</text>

    <rect x="40" y="157" width="690" height="30" fill="#0b1120" stroke="#1e293b" stroke-width="0.5"/>
    <text x="55" y="177" fill="#38bdf8" font-size="11" font-family="monospace">reports/annual_sales_2026.csv</text>
    <text x="350" y="177" fill="#cbd5e1" font-size="11" font-family="monospace">8.4 MB</text>
    <text x="450" y="177" fill="#cbd5e1" font-size="11" font-family="monospace">1.9 MB</text>
    <text x="560" y="177" fill="#34d399" font-size="11" font-weight="700">77.3%</text>
    <text x="650" y="177" fill="#94a3b8" font-size="11" font-family="monospace">b29e4198</text>

    <rect x="40" y="189" width="690" height="30" fill="#0f172a" stroke="#1e293b" stroke-width="0.5"/>
    <text x="55" y="209" fill="#38bdf8" font-size="11" font-family="monospace">images/catalog_hero.webp</text>
    <text x="350" y="209" fill="#cbd5e1" font-size="11" font-family="monospace">142 KB</text>
    <text x="450" y="209" fill="#cbd5e1" font-size="11" font-family="monospace">140 KB</text>
    <text x="560" y="209" fill="#94a3b8" font-size="11">1.4%</text>
    <text x="650" y="209" fill="#94a3b8" font-size="11" font-family="monospace">f7188c03</text>

    <!-- Zip Slip Protection Banner -->
    <rect x="40" y="235" width="690" height="50" rx="4" fill="#064e3b" stroke="#059669" stroke-width="1"/>
    <text x="60" y="257" fill="#6ee7b7" font-size="12" font-weight="700">✔ Zip-Slip Path Traversal Protection Active</text>
    <text x="60" y="274" fill="#a7f3d0" font-size="10.5">Entries containing directory traversal sequences ("..", null bytes, or outside symlinks) are strictly rejected.</text>

    <!-- Code Snippet -->
    <text x="40" y="315" fill="#38bdf8" font-size="11.5" font-family="monospace">Zip::extract('archive.zip', 'storage/extracted/'); // Safe extraction</text>
    <text x="40" y="335" fill="#a7f3d0" font-size="11.5" font-family="monospace">Zip::download('archive.zip', 'my_download.zip');   // Streaming HTTP download</text>
    <text x="40" y="355" fill="#64748b" font-size="10.5" font-family="monospace">Pure PHP ext-zip engine • Zero external compression libraries required</text>
SVG;
    return wrapSvg('Pure-PHP Zip Engine', 'terminal://bongo-cli/zip', $content);
};

// Execute all generators
$generatedCount = 0;
foreach (array_keys($generators) as $pkg) {
    if (!isset($generators[$pkg])) {
        echo "Warning: no generator for $pkg\n";
        continue;
    }

    $docsDir = $baseDir . DIRECTORY_SEPARATOR . $pkg . DIRECTORY_SEPARATOR . 'docs';
    if (!is_dir($docsDir)) {
        mkdir($docsDir, 0755, true);
    }

    $svgFile = $docsDir . DIRECTORY_SEPARATOR . 'screenshot.svg';
    $svgContent = $generators[$pkg]();
    file_put_contents($svgFile, $svgContent);
    $generatedCount++;

    // Update README.md
    $readmeFile = $baseDir . DIRECTORY_SEPARATOR . $pkg . DIRECTORY_SEPARATOR . 'README.md';
    if (is_file($readmeFile)) {
        $readme = (string) file_get_contents($readmeFile);
        if (!str_contains($readme, 'screenshot.svg')) {
            // Insert right after the top header or first paragraph
            $imgTag = "\n\n<p align=\"center\">\n  <img src=\"docs/screenshot.svg\" alt=\"{$pkg} screenshot\" width=\"800\">\n</p>\n";
            if (preg_match('/^(#[^\n]+\n+([^\n]+\n+)?)/m', $readme, $m)) {
                $readme = preg_replace('/^(#[^\n]+\n+([^\n]+\n+)?)/m', '$1' . $imgTag, $readme, 1);
                file_put_contents($readmeFile, $readme);
            }
        }
    }
}

echo "Successfully generated {$generatedCount} package screenshots!\n";
