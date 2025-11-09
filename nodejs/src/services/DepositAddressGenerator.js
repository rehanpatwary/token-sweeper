const ethers = require('ethers');
const crypto = require('crypto');

// Function to generate a new deposit address for a user
async function generateDepositAddress(userId) {
    // Generate a new random wallet
    const wallet = ethers.Wallet.createRandom();
    
    // Encrypt the private key before storing
    const encryptedPrivateKey = encryptPrivateKey(wallet.privateKey, process.env.ENCRYPTION_KEY);
    
    // Store in database
    await db.query(
        'INSERT INTO deposit_addresses (user_id, address, private_key_encrypted) VALUES ($1, $2, $3)',
        [userId, wallet.address, encryptedPrivateKey]
    );
    
    console.log(`Generated deposit address for user ${userId}: ${wallet.address}`);
    
    return {
        userId,
        address: wallet.address
    };
}

// Encryption function (use a proper encryption library in production)
function encryptPrivateKey(privateKey, encryptionKey) {
    const algorithm = 'aes-256-gcm';
    const iv = crypto.randomBytes(16);
    const cipher = crypto.createCipheriv(algorithm, Buffer.from(encryptionKey, 'hex'), iv);
    
    let encrypted = cipher.update(privateKey, 'utf8', 'hex');
    encrypted += cipher.final('hex');
    
    const authTag = cipher.getAuthTag();
    
    return JSON.stringify({
        iv: iv.toString('hex'),
        encryptedData: encrypted,
        authTag: authTag.toString('hex')
    });
}

// Decryption function
function decryptPrivateKey(encryptedData, encryptionKey) {
    const { iv, encryptedData: encrypted, authTag } = JSON.parse(encryptedData);
    
    const decipher = crypto.createDecipheriv(
        'aes-256-gcm',
        Buffer.from(encryptionKey, 'hex'),
        Buffer.from(iv, 'hex')
    );
    
    decipher.setAuthTag(Buffer.from(authTag, 'hex'));
    
    let decrypted = decipher.update(encrypted, 'hex', 'utf8');
    decrypted += decipher.final('utf8');
    
    return decrypted;
}

module.exports = { generateDepositAddress, decryptPrivateKey };