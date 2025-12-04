<?php
require __DIR__ . '/config.php';
require_role('consumer');

$me = $_SESSION['user'];

//YOUR CODES GO HERE

<!-- ====== Ethers.js (UMD build) & on-chain glue ====== -->
<script src="https://cdn.jsdelivr.net/npm/ethers@6.13.2/dist/ethers.umd.min.js"></script>
<script src="contract.js" defer></script>
