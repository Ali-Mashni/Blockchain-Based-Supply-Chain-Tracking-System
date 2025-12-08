// === CONFIG: fill with your deployed contract data ===

// Expect CONTRACT_ADDRESS and ABI to be provided by contract-config.js
const { CONTRACT_ADDRESS, ABI } = window.BSTS_CONFIG || {};

if (!CONTRACT_ADDRESS || !ABI) {
  console.error("BSTS_CONFIG is missing CONTRACT_ADDRESS or ABI.");
}

function toWei(ethString) {
  return ethers.parseUnits(String(ethString), 18);
}
async function resolveOnchainIdFromRow(row) {
  //  if already known, use and cache as BigInt
  if (row.dataset.rootId) return BigInt(row.dataset.rootId);

  //  build the same metaHash we emitted at approve time
  const fileId = row.dataset.srcId || row.dataset.id; // prefer supplier's src_id
  const name   = row.dataset.name;
  if (!name) throw new Error("Cannot resolve on-chain id: missing product name.");
  const metaHash = fileId ? `local:${fileId}|${name}` : null;

  const { contract } = await getSignerAndContract();
  const latest    = await contract.runner.provider.getBlockNumber();
  const fromBlock = Math.max(0, latest - 1_000_000);

  const events = await contract.queryFilter(contract.filters.ProductAdded(), fromBlock, latest);

  // 1) exact metaHash match (best)
  let hit = null;
  if (metaHash) hit = events.find(e => e?.args?.metaHash === metaHash) || null;

  // 2) fallback: name + exact price(wei) match
  if (!hit) {
    try {
      const priceWei = ethers.parseUnits(String(row.dataset.price), 18);
      const cands = events.filter(e => {
        const a = e?.args;
        if (!a) return false;
        const nameOk  = typeof a.metaHash === 'string' && a.metaHash.endsWith(`|${name}`);
        const priceOk = a.price === priceWei; // BigInt compare
        return nameOk && priceOk;
      });
      if (cands.length) hit = cands[cands.length - 1];
    } catch {}
  }

  // 3) give a clear error if truly not found
  if (!hit) {
    // small debug to help you see what's wrong
    console.warn("[resolver] No ProductAdded match", { metaHash, name, fileId, fromBlock, latest, eventsCount: events.length });
    throw new Error("Could not auto-resolve on-chain id. Re-approve / re-ship to capture it.");
  }

  const idStr = hit.args.id.toString();
  row.dataset.rootId = idStr; // cache for subsequent clicks
  return BigInt(idStr);
}



async function getSignerAndContract() {
  if (!window.ethereum) throw new Error("MetaMask not found");
  await ethereum.request({ method: "eth_requestAccounts" });

  const provider = new ethers.BrowserProvider(window.ethereum);
  const signer = await provider.getSigner();
  const contract = new ethers.Contract(CONTRACT_ADDRESS, ABI, signer);
  return { signer, contract };
}

// ---------- Producer approve: addProduct(...) ----------
async function handleApproveOnChain(row) {
  const id   = row.dataset.id;
  const name = row.dataset.name;
  const price = row.dataset.price; // ETH string
  const qty   = row.dataset.qty;   // string

  const metaHash = `local:${id}|${name}`;

  const { contract } = await getSignerAndContract();
  const tx = await contract.addProduct(metaHash, toWei(price), BigInt(qty));
  const receipt = await tx.wait();

  // Parse ProductAdded(id, owner, metaHash, price, qty)
  let onchainId = "";
  try {
    const iface = new ethers.Interface(ABI);
    for (const log of receipt.logs) {
      if ((log.address || "").toLowerCase() !== CONTRACT_ADDRESS.toLowerCase()) continue;
      try {
        const parsed = iface.parseLog(log);
        if (parsed?.name === "ProductAdded") {
          onchainId = parsed.args.id.toString();
          break;
        }
      } catch {}
    }
  } catch {}

  console.log('Parsed onchain id from logs:', onchainId);

  // Stash it in the approve form so PHP can save it
  const form = row.querySelector("form.approve-form");
  if (form) {
    const idInput = form.querySelector('input[name="onchain_id"]');
    if (idInput) idInput.value = onchainId;
  }

  return receipt?.hash || tx.hash;

}

function wireApproveButtons() {
  const buttons = document.querySelectorAll(".btn-onchain-approve");
  buttons.forEach((btn) => {
    btn.addEventListener("click", async () => {
      const row = btn.closest("tr");
      const form = row.querySelector("form.approve-form");
      const txInput = form.querySelector('input[name="txhash"]');

      btn.disabled = true;
      btn.textContent = "Approving…";

      try {
        const txhash = await handleApproveOnChain(row);
        txInput.value = txhash || "";
        form.submit();
      } catch (err) {
        console.error(err);
        alert(
          "On-chain approval failed: " + (err?.shortMessage || err?.message || err)
        );
        btn.disabled = false;
        btn.textContent = "Approve";
      }
    });
  });
}
// ---------- Consumer pays supplier (qty-aware) ----------
async function handleConsumerBuyOnChain(row) {
  const onchainId = await resolveOnchainIdFromRow(row);


  const qtyInput = row.querySelector('input[name="qty"]');
  const qty = BigInt(qtyInput.value);
  if (qty <= 0n) throw new Error("Quantity must be > 0");

  const { contract } = await getSignerAndContract();
  const p = await contract.getProduct(onchainId); // trust chain

  if (qty > p.qty) {
    throw new Error(`Requested ${qty} exceeds on-chain available ${p.qty}`);
  }

  const totalWei = p.price * qty;

  const tx = await contract.payConsumer(onchainId, qty, { value: totalWei });

  const receipt = await tx.wait();
  return receipt?.hash || tx.hash;
}


function wireConsumerBuyButtons() {
  const buttons = document.querySelectorAll(".btn-consumer-buy");
  buttons.forEach((btn) => {
    btn.addEventListener("click", async () => {
      const row = btn.closest("tr");
      const form = row.querySelector("form.consumer-buy-form");
      const txInput = form.querySelector('input[name="txhash"]');

      btn.disabled = true;
      btn.textContent = "Buying…";

      try {
        const txhash = await handleConsumerBuyOnChain(row);
        txInput.value = txhash || "";
        form.submit(); // PHP: deduct qty + save txhash
      } catch (err) {
        console.error(err);
        alert(
          "On-chain consumer payment failed: " +
          (err?.shortMessage || err?.message || err)
        );
        btn.disabled = false;
        btn.textContent = "Buy on chain";
      }
    });
  });
}

// ---------- Supplier pays producer (qty-aware) ----------
async function handleSupplierBuyOnChain(row) {
  // Use the real on-chain id captured at approval time
  const onchainId = await resolveOnchainIdFromRow(row);


  const qtyInput = row.querySelector('input[name="qty"]');
  const qty = BigInt(qtyInput.value);
  if (qty <= 0n) throw new Error("Quantity must be > 0");

  const { contract } = await getSignerAndContract();
  const p = await contract.getProduct(onchainId); // p.price is wei per unit

  if (qty > p.qty) {
    throw new Error(`Requested ${qty} exceeds on-chain available ${p.qty}`);
  }

  const totalWei = p.price * qty;

  const tx = await contract.paySupplier(onchainId, qty, { value: totalWei });

  const receipt = await tx.wait();
  return receipt?.hash || tx.hash;
}


function wireSupplierBuyButtons() {
  const buttons = document.querySelectorAll(".btn-onchain-buy");
  buttons.forEach((btn) => {
    btn.addEventListener("click", async () => {
      const row = btn.closest("tr");
      const form = row.querySelector("form.supplier-buy-form");
      const txInput = form.querySelector('input[name="txhash"]');

      btn.disabled = true;
      btn.textContent = "Buying…";

      try {
        const txhash = await handleSupplierBuyOnChain(row);
        txInput.value = txhash || "";
        form.submit(); // PHP: deduct qty + save suppliertx
      } catch (err) {
        console.error(err);
        alert(
          "On-chain supplier payment failed: " + (err?.shortMessage || err?.message || err)
        );
        btn.disabled = false;
        btn.textContent = "Buy on chain";
      }
    });
  });
}

// ---------- Balance helpers ----------
async function getMyOnChainBalance() {
  const { signer, contract } = await getSignerAndContract();
  const addr = await signer.getAddress();
  const balWei = await contract.balances(addr);
  const balEth = ethers.formatEther(balWei);
  return { addr, balWei, balEth };
}

async function withdrawMyBalance() {
  const { contract } = await getSignerAndContract();
  const tx = await contract.withdrawBalance();
  const receipt = await tx.wait();
  return receipt?.hash || tx.hash;
}

// Wire on load
window.addEventListener("load", () => {
  wireApproveButtons();
  wireSupplierBuyButtons();
  wireConsumerBuyButtons(); 
});
