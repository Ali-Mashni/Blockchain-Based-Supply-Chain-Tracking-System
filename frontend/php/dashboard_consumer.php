<?php
require __DIR__ . '/config.php';
require_role('consumer');

$me = $_SESSION['user'];

// Load products from text file
$all = get_products();

// Shipped products (from suppliers) that still have quantity
$available = array_filter($all, fn($r) =>
    ($r['status'] ?? '') === 'supplied' && (int)($r['qty'] ?? 0) > 0
);

// Products already purchased by this consumer
$mine = array_filter($all, fn($r) =>
    ($r['consumer'] ?? '') === $me['username']
);

$msg = '';

// ---- Search filter (by product / producer / supplier) ----
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $qLower = mb_strtolower($q);
    $available = array_filter($available, function ($r) use ($qLower) {
        return str_contains(mb_strtolower($r['name']), $qLower)
            || str_contains(mb_strtolower($r['owner']), $qLower)
            || str_contains(mb_strtolower($r['supplier']), $qLower);
    });
}

// ---- Handle purchases (after on-chain success) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $all = get_products(); // reload fresh

    if ($action === 'buy') {
        $id   = (int)($_POST['id'] ?? 0);
        $qty  = (int)($_POST['qty'] ?? 0);
        $txh  = trim($_POST['txhash'] ?? '');

        if ($id && $qty > 0 && isset($all[$id])) {
            // Deduct qty from the shipped product row
            $newQty = deduct_product_qty($id, $qty);
            $all = get_products(); // reload after deduction

            if ($newQty < 0) {
                $msg = "Failed: could not deduct quantity.";
            } else {
                $src   = $all[$id];
                $newid = next_product_id($all);

                // Record this purchase as a separate row
                $src   = $all[$id]; // shipped row we bought from
                $all[$newid] = [
                    'id'         => $newid,
                    'owner'      => $src['owner'],      // producer username
                    'supplier'   => $src['supplier'],   // supplier username
                    'consumer'   => $me['username'],    // THIS consumer
                    'ownertx'    => $src['ownertx'],    // producer tx
                    'suppliertx' => $txh,               // store consumer tx here
                    'name'       => $src['name'],
                    'price'      => $src['price'],
                    'qty'        => $qty,
                    'status'     => 'purchased',
                    'onchain_id' => $src['onchain_id'] ?? null, // keep linkage if present
                    'src_id'     => $src['src_id'] ?? null,     // link to original approved row if present
                    'updated_at' => now_iso(),
                ];



                $msg = save_products($all)
                    ? "Product purchased."
                    : "Failed to add purchase record.";
            }
        } else {
            $msg = "Invalid purchase request.";
        }
    }

    // Recompute lists after POST
    $all = get_products();
    $available = array_filter($all, fn($r) =>
        ($r['status'] ?? '') === 'supplied' && (int)($r['qty'] ?? 0) > 0
    );
    $mine = array_filter($all, fn($r) =>
        ($r['consumer'] ?? '') === $me['username']
    );
}

render_header("Consumer Dashboard");
?>
<div class="bar">
  <div>
    <h1>Consumer Dashboard</h1>
    <span class="tag"><?= h($me['role']) ?></span>
  </div>
  <div>
    <a href="login.php" style="margin-right:8px;text-decoration:none;">
      <button class="btn-secondary">Home</button>
    </a>
    <a href="logout.php"><button class="logout">Log out</button></a>
  </div>
</div>

<p class="sub">
  Welcome, <b><?= h($me['username']) ?></b>. Browse shipments created by suppliers and
  purchase your preferred quantity. On-chain payments are recorded in the
  <b>ProductsChain</b> contract on Sepolia.
</p>
<?php if ($msg): ?>
  <div class="<?= str_contains(strtolower($msg),'fail') ? 'msg' : 'ok' ?>"><?= $msg ?></div>
<?php endif; ?>

<!-- Search -->
<form method="get" action="dashboard_consumer.php"
      style="display:flex; gap:10px; margin: 10px 0 16px;">
  <input type="text" name="q" value="<?= h($q) ?>"
         placeholder="Search by product, producer, or supplier…" style="flex:1">
  <button type="submit">Search</button>
  <?php if ($q !== ''): ?>
    <a href="dashboard_consumer.php" style="text-decoration:none;">
      <button type="button" class="btn-secondary">Reset</button>
    </a>
  <?php endif; ?>
</form>

<style>
table {
  border-collapse: collapse;
  width: 100%;
}
table, th, td {
  border: 1px solid #1f2937;
  padding: 8px;
  text-align: left;
}
</style>

<h2 style="margin:20px 0 8px;">Available Products</h2>
<table>
  <tr>
    <th>ID</th><th>Product</th><th>Producer</th><th>Supplier</th>
    <th>Price (ETH)</th><th>Available</th><th>Purchase</th>
  </tr>
  <?php if (empty($available)): ?>
    <tr><td colspan="7" class="muted">No shipped products available.</td></tr>
  <?php else: ?>
    <?php foreach ($available as $p): ?>
        <?php
            $rootId = $p['onchain_id'] ?? null; // require true on-chain id
        ?>
        <tr data-id="<?= h($p['id']) ?>"
        data-root-id="<?= h($rootId ?? '') ?>"
        data-src-id="<?= h($p['src_id'] ?? '') ?>"
        data-name="<?= h($p['name']) ?>"
        data-price="<?= h($p['price']) ?>"
        data-qty="<?= h($p['qty']) ?>">
        <td>#<?= h($p['id']) ?></td>
        <td><?= h($p['name']) ?></td>
        <td><?= h($p['owner']) ?></td>
        <td><?= h($p['supplier']) ?></td>
        <td><?= h(number_format($p['price'], 4)) ?></td>
        <td><?= h($p['qty']) ?></td>
        <td>
          <form class="consumer-buy-form" method="post" action="dashboard_consumer.php">
            <input type="hidden" name="action" value="buy">
            <input type="hidden" name="id" value="<?= h($p['id']) ?>">
            <input type="hidden" name="txhash" value="">
            <input type="number" name="qty"
                   min="1" max="<?= h($p['qty']) ?>"
                   required style="width:80px">
            <button
            type="button"
            class="btn-consumer-buy"
            <?= empty($p['onchain_id']) ? 'title="Missing on-chain id (will auto-resolve from events)"' : '' ?>
          >Buy on chain</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  <?php endif; ?>
</table>

<h2 style="margin:24px 0 8px;">Your Products</h2>
<table>
  <?php if (empty($mine)): ?>
    <tr><td colspan="9" class="muted">You have not purchased any products yet.</td></tr>
  <?php else: ?>
    <tr>
      <th>ID</th><th>Product</th><th>Producer</th><th>Supplier</th>
      <th>Price (ETH)</th><th>Qty</th><th>Updated</th><th>Transaction</th><th>QR</th>
    </tr>
    <?php foreach ($mine as $r): ?>
      <?php
        // Build viewer URL with extra consumedAt param
        $viewerUrl = QR_VIEWER_BASE
          . '?id='         . urlencode($r['id'])
          . '&name='       . urlencode($r['name'])
          . '&qty='        . urlencode($r['qty'])
          . '&price='      . urlencode($r['price'])
          . '&status='     . urlencode($r['status'])      // "purchased"
          . '&consumedAt=' . urlencode($r['updated_at']); // time of purchase

        $qrImgUrl  = 'https://api.qrserver.com/v1/create-qr-code/?size=130x130&data='
                   . urlencode($viewerUrl);
      ?>
      <tr>
        <td>#<?= h($r['id']) ?></td>
        <td><?= h($r['name']) ?></td>
        <td><?= h($r['owner']) ?></td>
        <td><?= h($r['supplier']) ?></td>
        <td><?= h(number_format($r['price'], 4)) ?></td>
        <td><?= h($r['qty']) ?></td>
        <td class="muted"><?= h($r['updated_at']) ?></td>
        <td class="muted">
          <?php if (!empty($r['suppliertx'])): ?>
            <a href="https://sepolia.etherscan.io/tx/<?= h($r['suppliertx']) ?>" target="_blank">View</a>
          <?php else: ?>
            &mdash;
          <?php endif; ?>
        </td>
        <td>
          <a data-qr="1" href="<?= h($viewerUrl) ?>" target="_blank">
            <img src="<?= h($qrImgUrl) ?>"
                 alt="QR for purchased product #<?= h($r['id']) ?>"
                 style="width:90px;height:90px;cursor:pointer;">
          </a>
        </td>
      </tr>
    <?php endforeach; ?>
  <?php endif; ?>
</table>

<p class="muted" style="margin-top:10px;">
  Only products purchased by you are shown in the "Your Products" section.
</p>

<?php render_footer(); ?>

<!-- Ethers.js + on-chain glue -->
<script src="https://cdn.jsdelivr.net/npm/ethers@6.13.2/dist/ethers.umd.min.js"></script>
<script src="contract-config.js" defer></script>
<script src="contract.js" defer></script>
<script defer>
document.addEventListener('DOMContentLoaded', function () {
  const ca = (window.BSTS_CONFIG && window.BSTS_CONFIG.CONTRACT_ADDRESS) || '';
  if (!/^0x[a-fA-F0-9]{40}$/.test(ca)) return; // nothing to do

  document.querySelectorAll('a[data-qr="1"]').forEach(a => {
    try {
      const u = new URL(a.href, location.href);
      if (!u.searchParams.get('ca')) {
        u.searchParams.set('ca', ca);
        a.href = u.toString();
      }

      const img = a.querySelector('img');
      if (img && img.src.includes('api.qrserver.com')) {
        const qr = new URL(img.src, location.href);
        const dataParam = qr.searchParams.get('data') || a.href;
        const data = new URL(dataParam, location.href);
        data.searchParams.set('ca', ca);
        qr.searchParams.set('data', data.toString());
        img.src = qr.toString();
      }
    } catch(e){}
  });
});
</script>
