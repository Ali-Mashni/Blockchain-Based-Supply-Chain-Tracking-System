# Blockchain-Based Supply Chain Tracking System (BSTS)

A minimal dApp for supply-chain transparency built for Ethereum Sepolia with a local PHP (XAMPP) UI. Roles: Producer registers products, Supplier purchases quantities from producers, Consumer purchases from suppliers. Payments accrue to on-chain internal balances and can be withdrawn. Local app state (users, products) is stored in text files for simplicity.

---

## Table of Contents
- Overview
- Features
- Tech Stack
- Repository Layout
- Quick Start
- Configure the Contract
- Run the App (XAMPP)
- Demo Script
- QR Viewer (teammate handoff)
- Troubleshooting
- Security & Limits
- Roadmap

---

## Overview
BSTS demonstrates end-to-end flows:
- Add product on chain (producer) and publish it to the catalog.
- Supplier buys any quantity up to the available stock; producer’s contract balance increases.
- Consumer buys from supplier; supplier’s contract balance increases.
- Both producer and supplier can withdraw their balances to their wallets.
- Optional QR codes take any user to a read-only SPA that displays product info from chain.

---

## Features
- Role-based access (producer, supplier, consumer) enforced in the smart contract.
- Quantity-aware payments for supplier and consumer.
- On-chain internal balances with withdrawal.
- Flat-file users and products for a lightweight local stack.
- Single shared contract config file used by both the PHP app and the QR SPA.

---

## Tech Stack
- Solidity on Ethereum Sepolia, deployed via Remix and used with MetaMask and ethers.js (browser UMD).
- PHP served by XAMPP’s Apache for the local UI.
- Text files for users and products.

---

## Repository Layout
Use this as a reference for where files live when you copy the project under XAMPP’s htdocs.

```text
SUPPLYCHAIN/
├─ contracts/
│  └─ ProductsChain.sol
├─ docs/
│  └─ README.md   (this file)
├─ frontend\php/
│  ├─ config.php
│  ├─ contract-config.js
│  ├─ contract.js
│  ├─ dashboard_admin.php
│  ├─ dashboard_producer.php
│  ├─ dashboard_supplier.php
│  ├─ dashboard_consumer.php
│  ├─ dashboard.php
│  ├─ login.php
│  ├─ logout.php
│  ├─ users.txt
│  └─ products.txt
```

---

## Quick Start

1) Install and start XAMPP (Apache + PHP).

2) Copy the project to htdocs, for example:
   - C:\xampp\htdocs\ics440\

3) Open the app:
   - http://localhost/ics440/login.php

4) Log in as admin and create demo users:
   - Default admin: admin / admin123
   - Create prod1 (producer), sup1 (supplier), cons1 (consumer)

5) Make sure each MetaMask account has Sepolia ETH.

---

## Configure the Contract

1) Deploy ProductsChain.sol on Sepolia using Remix (Injected Provider – MetaMask).
2) Copy the deployed address and ABI.
3) Create or edit the shared config file used by all pages:

```javascript
// frontend\php\contract-config.js
window.BSTS_CONFIG = {
  CONTRACT_ADDRESS: "0xYOUR_SEPOLIA_CONTRACT",
  ABI: [ /* paste FULL ABI JSON here */ ]
};
```

4) Assign roles once (use the deployer/admin account in Remix):
   - setRole( producerAddress, 1 )   for Producer
   - setRole( supplierAddress, 2 )   for Supplier
   - setRole( consumerAddress, 3 )   for Consumer

5) Hard-refresh the browser if you redeploy (to avoid stale JS).

---

## Run the App (XAMPP)

- Start Apache.
- Browse to http://localhost/ics440/login.php
- Confirm MetaMask is on Sepolia.
- The app stores users and products in users.txt and products.txt (ensure Apache can write).

---

## Demo Script (Happy Path)

Producer
1) Log in as prod1.
2) Add product (for example: name Apples, price 0.0001, qty 100).
3) Click Approve → MetaMask pops → confirm.
4) Product appears as approved with an Etherscan link.

Supplier
1) Log in as sup1.
2) In Available Products, choose a product from prod1.
3) Enter quantity (≤ available) and click Buy on chain.
4) Contract credits producer’s internal balance and reduces remaining qty; app records a supplied row.

Consumer
1) Log in as cons1.
2) In Shipped Products, choose a supplier batch.
3) Enter quantity and click Buy on chain.
4) Contract credits supplier’s internal balance; app records a purchased row.

Withdraw
- From producer or supplier (console or small button you add), call:

```javascript
// helper functions available in contract.js
await getMyOnChainBalance(); // { addr, balWei, balEth }
await withdrawMyBalance();   // returns tx hash on success
```

---

## QR Viewer (teammate handoff)

Goal: a read-only SPA (GitHub Pages is fine) that shows product details given a URL like:
- https://yourname.github.io/bsts-qr/?id=1

What to reuse
- Copy the same contract-config.js into the SPA so both apps target the same contract.

Minimal SPA helper (read-only; no MetaMask required):

```javascript
// spa-contract.js
async function getReadOnlyContract() {
  const RPC_URL = "https://sepolia.infura.io/v3/YOUR_KEY";
  const provider = new ethers.JsonRpcProvider(RPC_URL);
  return new ethers.Contract(
    window.BSTS_CONFIG.CONTRACT_ADDRESS,
    window.BSTS_CONFIG.ABI,
    provider
  );
}
async function fetchProductById(id) {
  const c = await getReadOnlyContract();
  return c.getProduct(BigInt(id));
}
```

Minimal SPA logic:

```javascript
// index.html inline script (after including ethers UMD and the two JS files above)
async function main() {
  const id = new URLSearchParams(location.search).get("id");
  const root = document.getElementById("root");
  if (!id) { root.textContent = "Missing ?id"; return; }
  try {
    const p = await fetchProductById(id);
    root.textContent = [
      "Product #" + p.id,
      "Owner:     " + p.owner,
      "Supplier:  " + p.supplier,
      "Consumer:  " + p.consumer,
      "Meta:      " + p.metaHash,
      "PriceWei:  " + p.price,
      "Qty:       " + p.qty,
      "Approved:  " + p.approved
    ].join("\n");
  } catch (e) {
    root.textContent = "Error: " + (e.shortMessage || e.message || e);
  }
}
main();
```

The PHP app should generate QR codes that encode the SPA URL with the on-chain product id.

---

## Troubleshooting

Etherscan shows nothing
- Use the Sepolia explorer and a Sepolia tx hash. Check MetaMask network and the contract address in contract-config.js.

“not producer / supplier / consumer”
- Role not set for the current MetaMask address. Assign once in Remix as admin.

“not found/approved”
- You likely used a local row id instead of the on-chain product id. Reset by clearing products.txt, re-approving a fresh product.

“no matching fragment”
- ABI does not match deployed bytecode, or address is wrong. Re-copy ABI and update the config.

“insufficient funds”
- The caller wallet lacks Sepolia ETH for value + gas.

Price or quantity mismatches
- UI converts ETH strings to wei; contract treats price as wei per unit; total is price times quantity.

---

## Security & Limits
- This is a teaching demo: no production security, no database, no server hardening.
- Do not store private keys on the server in real systems.
- No upgrade path; contracts are immutable once deployed.

---

## Roadmap
- Persist on-chain id in the local rows to simplify lookups.
- Index contract events for a verifiable history page.
- Add UI buttons around balance checks and withdraw with toasts and loaders.
- Optional IPFS metadata.
- Automated tests and linting.
