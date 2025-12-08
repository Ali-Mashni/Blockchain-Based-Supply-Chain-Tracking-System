// SPDX-License-Identifier: GPL-3.0
pragma solidity >=0.8.2 <0.9.0;

contract ProductsChain {
    struct Product {
        uint256 id;
        address owner;      // current owner (last buyer or producer)
        address supplier;   // last supplier
        address consumer;   // last consumer
        string  ownertx;    // kept empty on chain; used off-chain in PHP
        string  suppliertx; // kept empty on chain; used off-chain in PHP
        string  metaHash;
        uint256 price;      // price per unit in WEI
        uint256 qty;        // remaining quantity (for demo)
        bool    approved;
        uint64  createdAt;
        uint64  updatedAt;
    }

    enum Role { None, Producer, Supplier, Consumer }

    address public admin;
    uint256 public nextProductId;
    mapping(uint256 => Product) public products;
    mapping(address => uint256) public balances;
    mapping(address => Role) public roles;

    event ProductAdded(
        uint256 indexed id,
        address indexed owner,
        string metaHash,
        uint256 price,
        uint256 qty
    );

    event SupplierPaid(
        uint256 indexed id,
        address indexed supplier,
        uint256 qty,
        uint256 totalPaid
    );

    event ConsumerPaid(
        uint256 indexed id,
        address indexed consumer,
        uint256 qty,
        uint256 totalPaid
    );

    event BalanceWithdrawn(address indexed account, uint256 amount);

    modifier onlyAdmin() {
        require(msg.sender == admin, "not admin");
        _;
    }

    constructor() {
        admin = msg.sender;
    }

    // ---- Roles: set once in Remix for demo accounts ----
    function setRole(address user, Role role) external onlyAdmin {
        roles[user] = role;
    }

    // ---------- Producer: register product ----------
    function addProduct(
        string calldata metaHash,
        uint256 price,
        uint256 qty
    ) external {
        require(roles[msg.sender] == Role.Producer, "not producer");
        require(qty > 0, "qty=0");
        require(price > 0, "price=0");

        uint256 id = ++nextProductId;

        products[id] = Product({
            id: id,
            owner: msg.sender,
            supplier: address(0),
            consumer: address(0),
            ownertx: "",
            suppliertx: "",
            metaHash: metaHash,
            price: price,
            qty: qty,
            approved: true,
            createdAt: uint64(block.timestamp),
            updatedAt: uint64(block.timestamp)
        });

        emit ProductAdded(id, msg.sender, metaHash, price, qty);
    }

    // ---------- Supplier: pay producer on chain for chosen qty ----------
    function paySupplier(uint256 productId, uint256 qty) external payable {
        Product storage p = products[productId];

        require(roles[msg.sender] == Role.Supplier, "not supplier");
        require(p.approved, "not approved");
        require(qty > 0, "qty=0");

        // Check against producer's available BEFORE transfer
        uint256 availableBefore = p.qty;
        require(qty <= availableBefore, "qty>available");

        // price per unit * qty
        uint256 total = p.price * qty;
        require(msg.value == total, "wrong amount");

        // Credit producer
        balances[p.owner] += msg.value;

        // Now turn THIS product into the supplier's lot:
        p.qty = qty;                    // the lot the supplier now has for resale
        p.supplier = msg.sender;
        p.owner = msg.sender;           // last owner is the supplier
        p.updatedAt = uint64(block.timestamp);

        emit SupplierPaid(productId, msg.sender, qty, msg.value);
    }

    // ---------- Consumer: pay supplier on chain for chosen qty ----------
    function payConsumer(uint256 id, uint256 qty) external payable {
        Product storage p = products[id];

        require(p.id != 0 && p.approved, "not found/approved");
        require(roles[msg.sender] == Role.Consumer, "not consumer");
        require(p.supplier != address(0), "no supplier yet");

        require(qty > 0, "qty=0");
        require(qty <= p.qty, "qty>available");

        uint256 total = p.price * qty;
        require(msg.value == total, "wrong amount");

        // Credit supplier balance
        balances[p.supplier] += msg.value;

        // Update product ownership & remaining qty
        p.consumer = msg.sender;
        p.owner = msg.sender;
        p.qty -= qty;
        p.updatedAt = uint64(block.timestamp);

        emit ConsumerPaid(id, msg.sender, qty, msg.value);
    }

    // ---------- Withdraw accumulated balances ----------
    function withdrawBalance() external {
        uint256 amount = balances[msg.sender];
        require(amount > 0, "no balance");
        balances[msg.sender] = 0;
        (bool ok, ) = msg.sender.call{value: amount}("");
        require(ok, "transfer failed");
        emit BalanceWithdrawn(msg.sender, amount);
    }

    // ---------- Views ----------
    function getProduct(uint256 id) external view returns (Product memory) {
        return products[id];
    }
}
