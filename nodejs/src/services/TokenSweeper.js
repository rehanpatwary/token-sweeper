const { ethers } = require('ethers');
const { decryptPrivateKey } = require('./depositAddressGenerator');

// Configuration
const provider = new ethers.JsonRpcProvider(process.env.RPC_URL);
const MASTER_WALLET = new ethers.Wallet(process.env.MASTER_PRIVATE_KEY, provider);
const HOT_WALLET_ADDRESS = process.env.HOT_WALLET_ADDRESS;

// ERC20 ABI
const ERC20_ABI = [
    "function transfer(address to, uint256 amount) returns (bool)",
    "function balanceOf(address account) view returns (uint256)",
    "function decimals() view returns (uint8)"
];

// Gas estimation (adjust based on network)
const ETH_FOR_GAS = ethers.parseEther("0.002"); // 0.002 ETH for gas

async function processSweep(depositAddress, tokenAddress, tokenName) {
    console.log(`\n🔄 Starting sweep process for ${depositAddress}`);
    
    try {
        // Update status to 'funding'
        await updateSweepStatus(depositAddress, tokenAddress, 'funding');
        
        // STEP 1: Send ETH for gas
        console.log(`Step 1: Funding ${depositAddress} with ETH for gas...`);
        const fundingTx = await fundAddressWithETH(depositAddress);
        console.log(`✅ Funded with ETH. TxHash: ${fundingTx.hash}`);
        
        // Wait for funding confirmation
        await fundingTx.wait(1);
        console.log(`✅ Funding confirmed`);
        
        // Small delay to ensure state update
        await sleep(2000);
        
        // Update status to 'sweeping'
        await updateSweepStatus(depositAddress, tokenAddress, 'sweeping');
        
        // STEP 2: Sweep tokens
        console.log(`Step 2: Sweeping ${tokenName} from ${depositAddress}...`);
        const sweepTx = await sweepTokens(depositAddress, tokenAddress);
        console.log(`✅ Tokens swept. TxHash: ${sweepTx.hash}`);
        
        // Wait for sweep confirmation
        await sweepTx.wait(1);
        console.log(`✅ Sweep completed successfully!`);
        
        // Update status to 'completed'
        await updateSweepStatus(depositAddress, tokenAddress, 'completed');
        
        // Optional: Sweep remaining ETH back to master wallet
        await sweepRemainingETH(depositAddress);
        
    } catch (error) {
        console.error(`❌ Sweep failed for ${depositAddress}:`, error.message);
        await updateSweepStatus(depositAddress, tokenAddress, 'failed');
        
        // Could implement retry logic here
        throw error;
    }
}

async function fundAddressWithETH(depositAddress) {
    // Check if address already has enough ETH
    const balance = await provider.getBalance(depositAddress);
    
    if (balance >= ETH_FOR_GAS) {
        console.log(`Address already has sufficient ETH: ${ethers.formatEther(balance)}`);
        return { hash: 'sufficient_balance', wait: async () => {} };
    }
    
    // Send ETH from master wallet
    const tx = await MASTER_WALLET.sendTransaction({
        to: depositAddress,
        value: ETH_FOR_GAS,
        gasLimit: 21000 // Standard ETH transfer
    });
    
    return tx;
}

async function sweepTokens(depositAddress, tokenAddress) {
    // Get encrypted private key from database
    const result = await db.query(
        'SELECT private_key_encrypted FROM deposit_addresses WHERE address = $1',
        [depositAddress]
    );
    
    if (result.rows.length === 0) {
        throw new Error(`Deposit address ${depositAddress} not found in database`);
    }
    
    // Decrypt private key
    const encryptedKey = result.rows[0].private_key_encrypted;
    const privateKey = decryptPrivateKey(encryptedKey, process.env.ENCRYPTION_KEY);
    
    // Create wallet instance
    const depositWallet = new ethers.Wallet(privateKey, provider);
    
    // Get token contract
    const tokenContract = new ethers.Contract(tokenAddress, ERC20_ABI, depositWallet);
    
    // Get token balance
    const balance = await tokenContract.balanceOf(depositAddress);
    
    if (balance === 0n) {
        throw new Error('No tokens to sweep');
    }
    
    // Execute transfer to hot wallet
    const tx = await tokenContract.transfer(HOT_WALLET_ADDRESS, balance, {
        gasLimit: 100000 // Adjust based on token
    });
    
    return tx;
}

async function sweepRemainingETH(depositAddress) {
    try {
        const result = await db.query(
            'SELECT private_key_encrypted FROM deposit_addresses WHERE address = $1',
            [depositAddress]
        );
        
        const encryptedKey = result.rows[0].private_key_encrypted;
        const privateKey = decryptPrivateKey(encryptedKey, process.env.ENCRYPTION_KEY);
        const depositWallet = new ethers.Wallet(privateKey, provider);
        
        const balance = await provider.getBalance(depositAddress);
        const gasPrice = (await provider.getFeeData()).gasPrice;
        const gasCost = gasPrice * 21000n;
        
        if (balance > gasCost) {
            const amountToSend = balance - gasCost;
            
            const tx = await depositWallet.sendTransaction({
                to: MASTER_WALLET.address,
                value: amountToSend,
                gasLimit: 21000
            });
            
            console.log(`♻️ Swept remaining ETH: ${ethers.formatEther(amountToSend)}`);
            await tx.wait(1);
        }
    } catch (error) {
        console.log('Could not sweep remaining ETH:', error.message);
    }
}

async function updateSweepStatus(depositAddress, tokenAddress, status) {
    await db.query(
        'UPDATE pending_sweeps SET status = $1 WHERE deposit_address = $2 AND token_address = $3 AND status != $4',
        [status, depositAddress, tokenAddress, 'completed']
    );
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

module.exports = { processSweep };