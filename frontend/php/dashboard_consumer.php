<?php
require __DIR__ . '/config.php';
require_role('consumer');

$me = $_SESSION['user'];
render_header("Consumer Dashboard");
?>
<div class="bar">
  <div>
    <h1>Consumer Dashboard</h1>
    <span class="tag"><?= h($me['role']) ?></span>
  </div>
  <div>
    <a href="login.php" class="btn-secondary" style="margin-right:8px;text-decoration:none;"><button class="btn-secondary">Home</button></a>
    <a href="logout.php"><button class="btn-danger">Log out</button></a>
  </div>
</div>

<p class="sub">Enter a product ID (later from QR) to verify its on-chain details.</p>

<label>Product ID</label>
<input id="prodId" type="number" min="1">
<button onclick="lookupProduct()">Lookup</button>

<pre id="result" class="muted" style="margin-top:12px;"></pre>

<?php render_footer(); ?>

<script src="https://cdn.jsdelivr.net/npm/ethers@6.13.2/dist/ethers.umd.min.js"></script>
<script src="contract.js"></script>
<script>
async function getProductFromChain(id) {
  const { contract } = await getSignerAndContract();
  return await contract.getProduct(BigInt(id));
}

async function lookupProduct(){
  const id = document.getElementById('prodId').value;
  if (!id) return;

  try {
    const p = await getProductFromChain(id);
    const text = [
      'On-chain ID: ' + p.id,
      'Owner:      ' + p.owner,
      'Supplier:   ' + p.supplier,
      'Consumer:   ' + p.consumer,
      'Meta hash:  ' + p.metaHash,
      'Price (wei): ' + p.price,
      'Qty:        ' + p.qty,
      'Approved:   ' + p.approved,
      'CreatedAt:  ' + p.createdAt,
      'UpdatedAt:  ' + p.updatedAt
    ].join('\n');
    document.getElementById('result').textContent = text;
  } catch (e) {
    alert('Failed to fetch product: ' + (e.shortMessage || e.message || e));
  }
}
</script>
