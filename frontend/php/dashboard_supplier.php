<?php
require __DIR__ . '/config.php';
require_role('supplier');

$me = $_SESSION['user'];

// Load products
$all = get_products();
$approved = array_filter($all, fn($r) => ($r['status'] ?? '') === 'approved' && (int)($r['qty'] ?? 0) > 0);
$mine     = array_filter($all, fn($r) => ($r['status'] ?? '') === 'supplied');

$msg = '';

// Search
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $qLower = mb_strtolower($q);
    $approved = array_filter($approved, function($r) use ($qLower) {
        return str_contains(mb_strtolower($r['name']),  $qLower) ||
               str_contains(mb_strtolower($r['owner']), $qLower);
    });
}

// Handle POST (supplier purchase)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $all    = get_products(); // reload fresh

    if ($action === 'approve') {
        $id   = (int)($_POST['id'] ?? 0);
        $txh  = trim($_POST['txhash'] ?? '');
        $qty  = (int)($_POST['qty'] ?? 0);
        $name = trim($_POST['name'] ?? '');

        if ($id && isset($all[$id])) {
            $newQty = deduct_product_qty($id, $qty); // local deduction

            $all = get_products(); // reload fresh after deduction

            if ($newQty < 0) {
                $msg = "failed: could not deduct qty.";
            } else {
                $newid = next_product_id($all);
                $all[$newid] = [
                    'id'         => $newid,
                    'owner'      => $all[$id]['owner'],
                    'supplier'   => $me['username'],
                    'consumer'   => $all[$id]['consumer'],
                    'ownertx'    => $all[$id]['ownertx'],
                    'suppliertx' => $txh,          // on-chain tx hash from MetaMask
                    'name'       => $name,
                    'price'      => $all[$id]['price'],
                    'qty'        => $qty,
                    'status'     => 'supplied',
                    'updated_at' => now_iso()
                ];

                $msg = save_products($all) ? "Product shipped." : "Failed to add product.";
            }
        }
    }

    // Refresh lists after changes
    $all      = get_products();
    $approved = array_filter($all, fn($r) => ($r['status'] ?? '') === 'approved' && (int)($r['qty'] ?? 0) > 0);
    $mine     = array_filter($all, fn($r) => ($r['status'] ?? '') === 'supplied');
}

render_header("Supplier Dashboard");
?>
<div class="bar">
  <div>
    <h1>Supplier Dashboard</h1>
    <span class="tag"><?= h($me['role']) ?></span>
  </div>
  <div>
    <a href="login.php" class="btn-secondary" style="margin-right:8px;text-decoration:none;">
      <button class="btn-secondary">Home</button>
    </a>
    <a href="logout.php"><button class="btn-danger">Log out</button></a>
  </div>
</div>

<p class="sub">
  Welcome, <b><?= h($me['username']) ?></b>. View approved products and purchase them on chain.
  On-chain actions are recorded on the Sepolia testnet.
</p>

<?php if ($msg): ?>
  <div class="<?= str_contains(strtolower($msg),'fail') ? 'msg' : 'ok' ?>">
    <?= $msg /* may contain plain text */ ?>
  </div>
<?php endif; ?>

<!-- Search -->
<form method="get" action="dashboard_supplier.php"
      style="display:flex; gap:10px; margin: 10px 0 16px;">
  <input type="text" name="q" value="<?= h($q) ?>"
         placeholder="Search by product or producer…" style="flex:1">
  <button type="submit">Search</button>
  <?php if ($q !== ''): ?>
    <a href="dashboard_supplier.php" class="btn-secondary" style="text-decoration:none;">
      <button type="button">Clear</button>
    </a>
  <?php endif; ?>
</form>

<p class="sub">Search approved products and buy your preferred quantity on chain.</p>

<style>
div.tablecontainer { overflow-x: auto; }
table { border-collapse: collapse; width: 100%; }
table, th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
</style>

<!-- Approved products -->
<h2 style="margin:20px 0 8px;">Available Products</h2>
<table>
  <tr>
    <th>ID</th><th>Product</th><th>Producer</th><th>Price (ETH)</th><th>Available</th><th>Purchase</th>
  </tr>
  <?php if (empty($approved)): ?>
    <tr><td colspan="6" class="muted">No approved products available.</td></tr>
  <?php else: ?>
    <?php foreach ($approved as $pid => $p): ?>
      <tr data-id="<?= h($p['id']) ?>"
          data-name="<?= h($p['name']) ?>"
          data-price="<?= h($p['price']) ?>"
          data-qty="<?= h($p['qty']) ?>">

        <td>#<?= h($p['id']) ?></td>
        <td><?= h($p['name']) ?></td>
        <td><?= h($p['owner']) ?></td>
        <td><?= h(number_format($p['price'], 4)) ?></td>
        <td><?= h($p['qty']) ?></td>
        <td>
          <!-- On-chain buy: JS calls paySupplier(), then submits with txhash -->
          <form class="supplier-buy-form" method="post" action="dashboard_supplier.php">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="id" value="<?= h($p['id']) ?>">
            <input type="hidden" name="name" value="<?= h($p['name']) ?>">
            <input type="hidden" name="txhash" value="">
            <input type="number" name="qty"
                   min="1" max="<?= h($p['qty']) ?>"
                   required style="width:80px">
            <button type="button" class="btn-onchain-buy">Buy on chain</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  <?php endif; ?>
</table>

<!-- My Shipments -->
<h2 style="margin:24px 0 8px;">Your Products</h2>
<table>
  <?php if (empty($mine)): ?>
    <tr><td colspan="7" class="muted">No products yet.</td></tr>
  <?php else: ?>
    <tr>
      <th>ID</th><th>Product</th><th>Price (ETH)</th><th>Qty</th>
      <th>Status</th><th>Updated</th><th>Transaction</th>
    </tr>
    <?php foreach ($mine as $r): ?>
      <?php if ($r['status'] === 'supplied'): ?>
        <tr data-id="<?= h($r['id']) ?>"
            data-name="<?= h($r['name']) ?>"
            data-price="<?= h($r['price']) ?>"
            data-qty="<?= h($r['qty']) ?>">

          <td>#<?= h($r['id']) ?></td>
          <td><?= h($r['name']) ?></td>
          <td><?= h(number_format($r['price'], 4)) ?></td>
          <td><?= h($r['qty']) ?></td>
          <td><?= h($r['status']) ?></td>
          <td class="muted"><?= h($r['updated_at']) ?></td>
          <td class="muted">
            <?php if (!empty($r['suppliertx'])): ?>
              <a href="https://sepolia.etherscan.io/tx/<?= h($r['suppliertx']) ?>" target="_blank">View</a>
            <?php else: ?>
              &mdash;
            <?php endif; ?>
          </td>
        </tr>
      <?php endif; ?>
    <?php endforeach; ?>
  <?php endif; ?>
</table>

<p class="muted" style="margin-top:10px;">
  Only your own shipped products are shown here (supplier = <?= h($me['username']) ?>).
</p>


<?php render_footer(); ?>

<!-- Ethers.js + contract config + helpers -->
<script src="https://cdn.jsdelivr.net/npm/ethers@6.13.2/dist/ethers.umd.min.js"></script>
<script src="contract-config.js"></script>
<script src="contract.js"></script>
