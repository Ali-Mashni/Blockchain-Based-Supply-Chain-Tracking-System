// SPDX-License-Identifier: GPL-3.0

pragma solidity >=0.8.2 <0.9.0;

/**
 * @title Storage
 * @dev Store & retrieve value in a variable
 * @custom:dev-run-script ./scripts/deploy_with_ethers.ts
 */
contract ProductsChain {

    struct Product {
        uint256 id;         // on-chain id (auto increment)
        address owner;      // producer address
        address supplier;   // supplier address
        address consumer;   // consumer address
        string  ownertx;    // producer transaction
        string suppliertx;  // supplier transaction
        string  metaHash;   // off-chain reference (e.g., IPFS CID or "local:<id>|<name>")
        uint256 price;      // price per unit in WEI
        uint256 qty;        // quantity approved/published
        bool    approved;   // true at creation for your flow
        uint64  createdAt;  // block timestamp (seconds)
        uint64  updatedAt;  // block timestamp (seconds)
    }

    uint256 public nextProductId;
    mapping(uint256 => Product) public products; // id => Product

    // ---------- Events ----------
    event ProductAdded(
        uint256 indexed id,
        address indexed owner,
        string metaHash,
        uint256 price,
        uint256 qty
    );

    // ---------- Functions ----------

    function addProduct(string calldata metaHash, uint256 price,uint256 qty) external  
    {
        require(qty > 0, "qty=0");
        // Optional: add sanity bounds on price

        uint256 id = ++nextProductId;

        products[id] = Product({
            id: id,
            owner: msg.sender,
            supplier: msg.sender,
            consumer: msg.sender,
            ownertx: "",
            suppliertx: "",
            metaHash: metaHash,
            price: price,
            qty: qty,
            approved: true,              // set approved=true at creation (fits your flow)
            createdAt: uint64(block.timestamp),
            updatedAt: uint64(block.timestamp)
        });

        emit ProductAdded(id, msg.sender, metaHash, price, qty);
        // Front-end reads tx hash from the transaction receipt
    }
    
    // ---------- Views ----------
    function getProduct(uint256 id) external view returns (Product memory)
    {
        return products[id];
    }
}