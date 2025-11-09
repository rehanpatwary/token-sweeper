const express = require('express');
const router = express.Router();
const DepositController = require('../controllers/DepositController');

// Deposit address endpoints
router.post('/deposit-address', DepositController.generateAddress);
router.get('/sweep-status/:address', DepositController.getSweepStatus);

module.exports = router;
