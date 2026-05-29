<template>
    <div>

        <!-- Summary cards -->
        <div class="row g-3 mb-3">
            <div v-for="card in summaryCards" :key="card.title" class="col-sm-6 col-xl-3">
                <div class="acct-stat-card">
                    <div class="acct-stat-icon"><i :class="card.icon"></i></div>
                    <div>
                        <p class="acct-stat-label">{{ card.title }}</p>
                        <h4 class="acct-stat-value">{{ card.value }}</h4>
                        <p class="acct-stat-note">{{ card.description }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inner tab bar -->
        <div class="cm-tab-bar mb-3">
            <button class="cm-tab-btn" :class="{ active: activeTab === 'cash_position' }" @click="activeTab = 'cash_position'">
                <i class="ri-money-dollar-circle-line me-1"></i>Cash Position
            </button>
            <button class="cm-tab-btn" :class="{ active: activeTab === 'fund_transfers' }" @click="activeTab = 'fund_transfers'">
                <i class="ri-exchange-dollar-line me-1"></i>Fund Transfers
            </button>
            <button class="cm-tab-btn" :class="{ active: activeTab === 'bank_deposits' }" @click="activeTab = 'bank_deposits'">
                <i class="ri-bank-card-2-line me-1"></i>Bank Deposits
            </button>
            <button class="cm-tab-btn" :class="{ active: activeTab === 'petty_cash' }" @click="activeTab = 'petty_cash'">
                <i class="ri-wallet-3-line me-1"></i>Petty Cash
            </button>
        </div>

        <!-- ── Cash Position ──────────────────────────────────────── -->
        <template v-if="activeTab === 'cash_position'">
            <div v-if="!cashPosition.data_ready" class="cm-empty-state">
                <i class="ri-information-line"></i>
                <p class="mb-1">Accounting tables not set up yet</p>
                <small>Run accounting migrations to see live cash balances.</small>
            </div>
            <template v-else>
                <!-- Total cash card -->
                <div class="cash-total-card mb-3">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div>
                            <p class="cash-total-label mb-1">Total Cash Position</p>
                            <h2 class="cash-total-value mb-0">{{ formatCurrency(cashPosition.total_cash) }}</h2>
                        </div>
                        <div class="d-flex gap-4">
                            <div class="text-end">
                                <p class="cash-sub-label mb-0">Bank Accounts</p>
                                <p class="cash-sub-value mb-0">{{ formatCurrency(cashPosition.total_bank) }}</p>
                            </div>
                            <div class="text-end">
                                <p class="cash-sub-label mb-0">Petty Cash</p>
                                <p class="cash-sub-value mb-0">{{ formatCurrency(cashPosition.total_petty_cash) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bank accounts -->
                <div class="library-card mb-3">
                    <div class="library-card-header">
                        <div class="d-flex align-items-center gap-3">
                            <div class="header-icon"><i class="ri-bank-line"></i></div>
                            <div>
                                <h4 class="header-title mb-0">Bank Accounts</h4>
                                <p class="header-subtitle mb-0">Current balance per bank account from GL entries.</p>
                            </div>
                        </div>
                    </div>
                    <div class="library-card-body p-0">
                        <div v-if="cashPosition.bank_balances.length === 0" class="cm-empty-state">
                            <i class="ri-bank-line"></i>
                            <p>No bank accounts configured. Add one in Settings.</p>
                        </div>
                        <div v-else class="table-responsive">
                            <table class="table cm-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Bank</th>
                                        <th>Account Name</th>
                                        <th>GL Code</th>
                                        <th class="text-end">Balance</th>
                                        <th class="text-center">GL Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="b in cashPosition.bank_balances" :key="b.id">
                                        <td class="fw-semibold">{{ b.bank_name }}</td>
                                        <td>{{ b.account_name }}</td>
                                        <td class="font-monospace text-muted">{{ b.gl_code }}</td>
                                        <td class="text-end fw-semibold" :class="b.balance < 0 ? 'text-danger' : ''">{{ b.balance_formatted }}</td>
                                        <td class="text-center">
                                            <span class="gl-status-chip" :class="b.has_account ? 'mapped' : 'unmapped'">
                                                {{ b.has_account ? 'Mapped' : 'No GL' }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="total-row">
                                        <td colspan="3" class="fw-bold">Total Bank Balance</td>
                                        <td class="text-end fw-bold">{{ formatCurrency(cashPosition.total_bank) }}</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Petty Cash -->
                <div v-if="cashPosition.petty_cash.length > 0" class="library-card">
                    <div class="library-card-header">
                        <div class="d-flex align-items-center gap-3">
                            <div class="header-icon"><i class="ri-wallet-3-line"></i></div>
                            <div>
                                <h4 class="header-title mb-0">Petty Cash Funds</h4>
                                <p class="header-subtitle mb-0">Current balance per petty cash fund.</p>
                            </div>
                        </div>
                    </div>
                    <div class="library-card-body p-0">
                        <div class="table-responsive">
                            <table class="table cm-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Fund Name</th>
                                        <th>GL Code</th>
                                        <th class="text-end">Balance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="f in cashPosition.petty_cash" :key="f.id">
                                        <td class="fw-semibold">{{ f.name }}</td>
                                        <td class="font-monospace text-muted">{{ f.gl_code }}</td>
                                        <td class="text-end fw-semibold" :class="f.balance < 0 ? 'text-danger' : ''">{{ f.balance_formatted }}</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="total-row">
                                        <td colspan="2" class="fw-bold">Total Petty Cash</td>
                                        <td class="text-end fw-bold">{{ formatCurrency(cashPosition.total_petty_cash) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </template>
        </template>

        <!-- ── Fund Transfers ──────────────────────────────────────── -->
        <template v-if="activeTab === 'fund_transfers'">
            <div class="library-card">
                <div class="library-card-header">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-3">
                            <div class="header-icon"><i class="ri-exchange-dollar-line"></i></div>
                            <div>
                                <h4 class="header-title mb-0">Fund Transfers</h4>
                                <p class="header-subtitle mb-0">Move funds between bank accounts with automatic journal entries.</p>
                            </div>
                        </div>
                        <button class="acct-btn-primary" @click="openCreateTransfer">
                            <i class="ri-add-line"></i> New Transfer
                        </button>
                    </div>
                </div>
                <div class="library-card-body p-0">
                    <div v-if="transfers.length === 0" class="cm-empty-state">
                        <i class="ri-exchange-dollar-line"></i>
                        <p class="mb-1">No fund transfers recorded</p>
                        <small>Record your first bank-to-bank transfer.</small>
                    </div>
                    <div v-else class="table-responsive">
                        <table class="table cm-table mb-0">
                            <thead>
                                <tr>
                                    <th>Transfer No</th>
                                    <th>Date</th>
                                    <th>From</th>
                                    <th>To</th>
                                    <th class="text-end">Amount</th>
                                    <th>Reference</th>
                                    <th>Notes</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="t in transfers" :key="t.id">
                                    <td class="font-monospace text-muted">{{ t.transfer_no }}</td>
                                    <td>{{ t.transfer_date }}</td>
                                    <td><span class="bank-chip from">{{ t.from_bank }}</span></td>
                                    <td><span class="bank-chip to">{{ t.to_bank }}</span></td>
                                    <td class="text-end fw-semibold">{{ t.amount_formatted }}</td>
                                    <td class="text-muted">{{ t.reference_number || '—' }}</td>
                                    <td class="text-muted small">{{ t.notes || '—' }}</td>
                                    <td class="text-center">
                                        <button class="action-btn delete" @click="confirmDeleteTransfer(t)" title="Delete & Reverse">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Fund Transfer Modal -->
            <div v-if="ftModal.open" class="modal-overlay active" @click.self="ftModal.open = false">
                <div class="modal-container" style="max-width:540px">
                    <div class="modal-header">
                        <div class="modal-header-icon"><i class="ri-exchange-dollar-line"></i></div>
                        <div>
                            <h5 class="modal-title">New Fund Transfer</h5>
                            <p class="modal-subtitle">Record a bank-to-bank transfer. A journal entry will be posted automatically.</p>
                        </div>
                        <button class="close-btn ms-auto" @click="ftModal.open = false"><i class="ri-close-line"></i></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Transfer Date <span class="text-danger">*</span></label>
                                <input v-model="ftForm.transfer_date" type="date" class="form-control" />
                                <div v-if="ftErrors.transfer_date" class="error-msg">{{ ftErrors.transfer_date[0] }}</div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Amount <span class="text-danger">*</span></label>
                                <input v-model="ftForm.amount" type="number" step="0.01" min="0.01" class="form-control" placeholder="0.00" />
                                <div v-if="ftErrors.amount" class="error-msg">{{ ftErrors.amount[0] }}</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">From Bank Account <span class="text-danger">*</span></label>
                                <select v-model="ftForm.from_bank_account_id" class="form-select">
                                    <option value="">-- Select source bank --</option>
                                    <option v-for="b in bankAccounts" :key="b.id" :value="b.id">{{ b.bank_name }} — {{ b.account_name }}</option>
                                </select>
                                <div v-if="ftErrors.from_bank_account_id" class="error-msg">{{ ftErrors.from_bank_account_id[0] }}</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">To Bank Account <span class="text-danger">*</span></label>
                                <select v-model="ftForm.to_bank_account_id" class="form-select">
                                    <option value="">-- Select destination bank --</option>
                                    <option v-for="b in bankAccounts" :key="b.id" :value="b.id" :disabled="b.id === ftForm.from_bank_account_id">{{ b.bank_name }} — {{ b.account_name }}</option>
                                </select>
                                <div v-if="ftErrors.to_bank_account_id" class="error-msg">{{ ftErrors.to_bank_account_id[0] }}</div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Reference No <span class="text-muted">(optional)</span></label>
                                <input v-model="ftForm.reference_number" type="text" class="form-control" placeholder="Bank ref / cheque no" />
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Notes <span class="text-muted">(optional)</span></label>
                                <input v-model="ftForm.notes" type="text" class="form-control" placeholder="Internal memo" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="acct-btn-secondary" @click="ftModal.open = false">Cancel</button>
                        <button class="acct-btn-primary" :disabled="ftSaving" @click="submitTransfer">
                            <span v-if="ftSaving"><i class="ri-loader-4-line spin"></i> Saving...</span>
                            <span v-else>Record Transfer</span>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- ── Bank Deposits ──────────────────────────────────────── -->
        <template v-if="activeTab === 'bank_deposits'">
            <div class="library-card">
                <div class="library-card-header">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-3">
                            <div class="header-icon"><i class="ri-bank-card-2-line"></i></div>
                            <div>
                                <h4 class="header-title mb-0">Bank Deposits</h4>
                                <p class="header-subtitle mb-0">Record cash deposited to bank. Posts DR Bank / CR Cash on Hand automatically.</p>
                            </div>
                        </div>
                        <button class="acct-btn-primary" @click="openCreateDeposit">
                            <i class="ri-add-line"></i> New Deposit
                        </button>
                    </div>
                </div>
                <div class="library-card-body p-0">
                    <div v-if="deposits.length === 0" class="cm-empty-state">
                        <i class="ri-bank-card-2-line"></i>
                        <p class="mb-1">No bank deposits recorded</p>
                        <small>Record cash collected by the owner or sales rep deposited to a bank account.</small>
                    </div>
                    <div v-else class="table-responsive">
                        <table class="table cm-table mb-0">
                            <thead>
                                <tr>
                                    <th>Deposit No</th>
                                    <th>Date</th>
                                    <th>Cash Account</th>
                                    <th>Deposited To</th>
                                    <th class="text-end">Amount</th>
                                    <th>Reference</th>
                                    <th>Recorded By</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="d in deposits" :key="d.id">
                                    <td class="font-monospace deposit-no">{{ d.deposit_no }}</td>
                                    <td class="text-nowrap">{{ d.deposit_date }}</td>
                                    <td class="text-muted small">{{ d.cash_account }}</td>
                                    <td><span class="bank-chip to">{{ d.bank_name }}</span></td>
                                    <td class="text-end fw-semibold deposit-amount">{{ d.amount_formatted }}</td>
                                    <td class="text-muted">{{ d.reference || '—' }}</td>
                                    <td class="text-muted small">{{ d.created_by }}</td>
                                    <td class="text-center">
                                        <button class="action-btn delete" @click="confirmDeleteDeposit(d)" title="Delete & Reverse">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Bank Deposit Modal -->
            <div v-if="bdModal.open" class="modal-overlay active" @click.self="bdModal.open = false">
                <div class="modal-container" style="max-width:520px">
                    <div class="modal-header">
                        <div class="modal-header-icon"><i class="ri-bank-card-2-line"></i></div>
                        <div>
                            <h5 class="modal-title">Record Bank Deposit</h5>
                            <p class="modal-subtitle">Cash on hand deposited to a bank account. Posts DR Bank / CR Cash automatically.</p>
                        </div>
                        <button class="close-btn ms-auto" @click="bdModal.open = false"><i class="ri-close-line"></i></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Deposit Date <span class="text-danger">*</span></label>
                                <input v-model="bdForm.deposit_date" type="date" class="form-control" />
                                <div v-if="bdErrors.deposit_date" class="error-msg">{{ bdErrors.deposit_date[0] }}</div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Amount <span class="text-danger">*</span></label>
                                <input v-model="bdForm.amount" type="number" step="0.01" min="0.01" class="form-control" placeholder="0.00" />
                                <div v-if="bdErrors.amount" class="error-msg">{{ bdErrors.amount[0] }}</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Cash Account (Source) <span class="text-danger">*</span></label>
                                <select v-model="bdForm.cash_account_id" class="form-select">
                                    <option value="">-- Select cash GL account --</option>
                                    <option v-for="a in cashAccounts" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option>
                                </select>
                                <div class="form-text">The cash account that will be reduced (credited).</div>
                                <div v-if="bdErrors.cash_account_id" class="error-msg">{{ bdErrors.cash_account_id[0] }}</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Deposit To (Bank Account) <span class="text-danger">*</span></label>
                                <select v-model="bdForm.bank_account_id" class="form-select">
                                    <option value="">-- Select bank account --</option>
                                    <option v-for="b in bankAccounts" :key="b.id" :value="b.id">{{ b.bank_name }} — {{ b.account_name }}</option>
                                </select>
                                <div v-if="bdErrors.bank_account_id" class="error-msg">{{ bdErrors.bank_account_id[0] }}</div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Reference No <span class="text-muted">(optional)</span></label>
                                <input v-model="bdForm.reference" type="text" class="form-control" placeholder="Deposit slip / OR no." />
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Notes <span class="text-muted">(optional)</span></label>
                                <input v-model="bdForm.notes" type="text" class="form-control" placeholder="e.g. Collections from Jan 15–20" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="acct-btn-secondary" @click="bdModal.open = false">Cancel</button>
                        <button class="acct-btn-primary" :disabled="bdSaving" @click="submitDeposit">
                            <span v-if="bdSaving"><i class="ri-loader-4-line spin"></i> Saving...</span>
                            <span v-else>Record Deposit</span>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- ── Petty Cash ──────────────────────────────────────────── -->
        <template v-if="activeTab === 'petty_cash'">

            <!-- No funds yet -->
            <div v-if="funds.length === 0 && !pcFundModal.open" class="cm-empty-state mb-3">
                <i class="ri-wallet-3-line"></i>
                <p class="mb-2">No petty cash funds set up</p>
                <button class="acct-btn-primary" @click="openCreateFund">
                    <i class="ri-add-line"></i> Set Up Petty Cash Fund
                </button>
            </div>

            <template v-else>
                <!-- Fund selector + new fund button -->
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <label class="form-label mb-0 fw-semibold">Fund:</label>
                        <select v-model="selectedFundId" class="form-select form-select-sm" style="min-width:220px">
                            <option v-for="f in funds" :key="f.id" :value="f.id">{{ f.name }} ({{ f.balance_formatted }})</option>
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="acct-btn-secondary" @click="openCreateFund">
                            <i class="ri-add-line"></i> New Fund
                        </button>
                        <button class="acct-btn-primary" @click="openAddTransaction('replenishment')" :disabled="!selectedFund">
                            <i class="ri-arrow-down-circle-line"></i> Replenish Fund
                        </button>
                    </div>
                </div>

                <!-- Fund balance card -->
                <div v-if="selectedFund" class="fund-balance-card mb-3">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-3">
                            <div class="fund-balance-icon"><i class="ri-wallet-3-line"></i></div>
                            <div>
                                <p class="fund-name mb-0">{{ selectedFund.name }}</p>
                                <p class="fund-gl mb-0">GL: {{ selectedFund.gl_code }}</p>
                                <p class="fund-gl mb-0" v-if="selectedFund.weekly_budget > 0">
                                    Weekly Budget: <strong style="color:#a8e6cf">{{ formatCurrency(selectedFund.weekly_budget) }}</strong>
                                    <button class="fund-edit-btn ms-2" @click="openEditFund(selectedFund)" title="Edit budget">
                                        <i class="ri-pencil-line"></i>
                                    </button>
                                </p>
                                <p class="fund-gl mb-0" v-else>
                                    No weekly budget set
                                    <button class="fund-edit-btn ms-2" @click="openEditFund(selectedFund)" title="Set weekly budget">
                                        <i class="ri-add-line"></i> Set Budget
                                    </button>
                                </p>
                            </div>
                        </div>
                        <div class="text-end">
                            <p class="fund-balance-label mb-0">Current Balance</p>
                            <h3 class="fund-balance-value mb-0" :class="selectedFund.balance < 0 ? 'text-danger' : ''">{{ selectedFund.balance_formatted }}</h3>
                        </div>
                    </div>
                    <!-- Weekly budget usage bar -->
                    <template v-if="selectedFund.weekly_budget > 0 && weeklyRemaining !== null">
                        <div class="week-budget-bar-wrap mt-3">
                            <div class="d-flex justify-content-between mb-1" style="font-size:0.75rem;color:#527267">
                                <span>This week's spending</span>
                                <span class="fw-semibold" style="color:#1a4d3d">{{ formatCurrency(weeklySpent) }} / {{ formatCurrency(selectedFund.weekly_budget) }}</span>
                            </div>
                            <div class="week-budget-bar">
                                <div class="week-budget-fill" :style="weeklyBarStyle"></div>
                            </div>
                            <div class="d-flex justify-content-between mt-1" style="font-size:0.72rem;color:#6b8c85">
                                <span v-if="weeklyRemaining >= 0">Remaining: <strong style="color:#1a6b4a">{{ formatCurrency(weeklyRemaining) }}</strong></span>
                                <span v-else style="color:#b91c1c;font-weight:700">Over budget by {{ formatCurrency(Math.abs(weeklyRemaining)) }}</span>
                                <span v-if="weeklyRemaining < 0" style="color:#9a3b1b;font-style:italic">Can still request additional budget</span>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Transactions table -->
                <div class="library-card">
                    <div class="library-card-header">
                        <div class="d-flex align-items-center gap-3">
                            <div class="header-icon"><i class="ri-list-check"></i></div>
                            <div>
                                <h4 class="header-title mb-0">Transactions</h4>
                                <p class="header-subtitle mb-0">Replenishments and disbursements for this fund.</p>
                            </div>
                        </div>
                    </div>
                    <div class="library-card-body p-0">
                        <div v-if="!selectedFund || selectedFund.transactions.length === 0" class="cm-empty-state">
                            <i class="ri-receipt-line"></i>
                            <p class="mb-1">No transactions yet</p>
                            <small>Replenish the fund or record a disbursement to get started.</small>
                        </div>
                        <div v-else class="table-responsive">
                            <table class="table cm-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Txn No</th>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Category</th>
                                        <th>Description</th>
                                        <th class="text-end">Amount</th>
                                        <th>Ref / Source</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="t in selectedFund.transactions" :key="t.id">
                                        <td class="font-monospace text-muted">{{ t.transaction_no }}</td>
                                        <td>{{ t.transaction_date }}</td>
                                        <td>
                                            <span class="txn-type-chip" :class="t.type">{{ t.type === 'replenishment' ? 'Replenishment' : 'Disbursement' }}</span>
                                        </td>
                                        <td class="text-muted">{{ t.category || '—' }}</td>
                                        <td class="text-muted small">{{ t.description || '—' }}</td>
                                        <td class="text-end fw-semibold" :class="t.type === 'disbursement' ? 'text-danger' : 'text-success'">
                                            {{ t.type === 'disbursement' ? '−' : '+' }}{{ t.amount_formatted }}
                                        </td>
                                        <td class="text-muted small">
                                            <span v-if="t.reference_number">{{ t.reference_number }}</span>
                                            <span v-else-if="t.bank_account_name && t.bank_account_name !== ' — '">{{ t.bank_account_name }}</span>
                                            <span v-else-if="t.source_type">{{ t.source_type }}</span>
                                            <span v-else>—</span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex gap-1 justify-content-center">
                                                <a v-if="t.receipt_path" :href="'/storage/' + t.receipt_path" target="_blank" class="action-btn view" title="View Receipt">
                                                    <i class="ri-attachment-2"></i>
                                                </a>
                                                <button class="action-btn delete" @click="confirmDeleteTransaction(t)" title="Delete & Reverse">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Create Fund Modal -->
            <div v-if="pcFundModal.open" class="modal-overlay active" @click.self="pcFundModal.open = false">
                <div class="modal-container" style="max-width:440px">
                    <div class="modal-header">
                        <div class="modal-header-icon"><i class="ri-wallet-3-line"></i></div>
                        <div>
                            <h5 class="modal-title">Create Petty Cash Fund</h5>
                            <p class="modal-subtitle">Set up a new petty cash fund with a dedicated GL account.</p>
                        </div>
                        <button class="close-btn ms-auto" @click="pcFundModal.open = false"><i class="ri-close-line"></i></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Fund Name <span class="text-danger">*</span></label>
                                <input v-model="pcFundForm.name" type="text" class="form-control" placeholder="e.g. Main Office Petty Cash" />
                                <div v-if="pcFundErrors.name" class="error-msg">{{ pcFundErrors.name[0] }}</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">GL Code <span class="text-danger">*</span></label>
                                <input v-model="pcFundForm.gl_code" type="text" class="form-control font-monospace" placeholder="e.g. 1050" />
                                <div class="form-text">Unique GL code for this petty cash account.</div>
                                <div v-if="pcFundErrors.gl_code" class="error-msg">{{ pcFundErrors.gl_code[0] }}</div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Initial Balance <span class="text-muted">(optional)</span></label>
                                <input v-model="pcFundForm.initial_balance" type="number" step="0.01" min="0.01" class="form-control" placeholder="0.00" />
                                <div class="form-text">Posts DR Petty Cash / CR Cash on Hand.</div>
                                <div v-if="pcFundErrors.initial_balance" class="error-msg">{{ pcFundErrors.initial_balance[0] }}</div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Weekly Budget <span class="text-muted">(optional)</span></label>
                                <input v-model="pcFundForm.weekly_budget" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="acct-btn-secondary" @click="pcFundModal.open = false">Cancel</button>
                        <button class="acct-btn-primary" :disabled="pcFundSaving" @click="submitFund">
                            <span v-if="pcFundSaving"><i class="ri-loader-4-line spin"></i> Saving...</span>
                            <span v-else>Create Fund</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Edit Fund Modal -->
            <div v-if="fundEditModal.open" class="modal-overlay active" @click.self="fundEditModal.open = false">
                <div class="modal-container" style="max-width:420px">
                    <div class="modal-header">
                        <div class="modal-header-icon"><i class="ri-pencil-line"></i></div>
                        <div>
                            <h5 class="modal-title">Edit Fund Settings</h5>
                            <p class="modal-subtitle">Update the fund name and weekly budget.</p>
                        </div>
                        <button class="close-btn ms-auto" @click="fundEditModal.open = false"><i class="ri-close-line"></i></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Fund Name <span class="text-danger">*</span></label>
                                <input v-model="fundEditForm.name" type="text" class="form-control" placeholder="Fund name" />
                            </div>
                            <div class="col-12">
                                <label class="form-label">Weekly Budget <span class="text-muted">(optional)</span></label>
                                <input v-model="fundEditForm.weekly_budget" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" />
                                <div class="form-text">Set to 0 to remove the weekly budget limit.</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="acct-btn-secondary" @click="fundEditModal.open = false">Cancel</button>
                        <button class="acct-btn-primary" :disabled="fundEditSaving" @click="submitEditFund">
                            <span v-if="fundEditSaving"><i class="ri-loader-4-line spin"></i> Saving...</span>
                            <span v-else>Save Changes</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Add Transaction Modal -->
            <div v-if="pcTxnModal.open" class="modal-overlay active" @click.self="pcTxnModal.open = false">
                <div class="modal-container" style="max-width:500px">
                    <div class="modal-header">
                        <div class="modal-header-icon"><i class="ri-arrow-down-circle-line"></i></div>
                        <div>
                            <h5 class="modal-title">Replenish Fund</h5>
                            <p class="modal-subtitle">Finance transfers cash to the petty cash custodian. Posts DR Petty Cash / CR Cash automatically.</p>
                        </div>
                        <button class="close-btn ms-auto" @click="pcTxnModal.open = false"><i class="ri-close-line"></i></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <!-- Replenishment request selector -->
                            <div class="col-12">
                                <label class="form-label">
                                    Replenishment Request
                                    <span class="text-muted" style="font-weight:400;">(optional — auto-fills amount)</span>
                                </label>
                                <select v-model="selectedRepRequest" class="form-select" @change="selectRepRequest" :disabled="repRequestsLoading">
                                    <option value="">{{ repRequestsLoading ? 'Loading...' : repRequests.length ? '— Select to auto-fill —' : '— No approved requests for this fund —' }}</option>
                                    <option v-for="r in repRequests" :key="r.id" :value="r.id">
                                        {{ r.reference_no }} — ₱{{ Number(r.total_amount).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }} ({{ r.expense_count }} expense{{ r.expense_count !== 1 ? 's' : '' }})
                                    </option>
                                </select>
                            </div>
                            <div class="col-12" v-if="repRequests.length"><hr class="my-0" style="border-color:#e4f0ea;"></div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Date <span class="text-danger">*</span></label>
                                <input v-model="pcTxnForm.transaction_date" type="date" class="form-control" />
                                <div v-if="pcTxnErrors.transaction_date" class="error-msg">{{ pcTxnErrors.transaction_date[0] }}</div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Amount <span class="text-danger">*</span></label>
                                <input v-model="pcTxnForm.amount" type="number" step="0.01" min="0.01" class="form-control" placeholder="0.00" />
                                <div v-if="pcTxnErrors.amount" class="error-msg">{{ pcTxnErrors.amount[0] }}</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Funded From</label>
                                <select v-model="pcTxnForm.source_type" class="form-select">
                                    <option value="cash">Cash on Hand</option>
                                    <option value="bank">Bank Transfer</option>
                                </select>
                            </div>
                            <div v-if="pcTxnForm.source_type === 'bank'" class="col-12">
                                <label class="form-label">Bank Account</label>
                                <select v-model="pcTxnForm.bank_account_id" class="form-select">
                                    <option value="">-- Select bank --</option>
                                    <option v-for="b in bankAccounts" :key="b.id" :value="b.id">{{ b.bank_name }} — {{ b.account_name }}</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description <span class="text-muted">(optional)</span></label>
                                <input v-model="pcTxnForm.description" type="text" class="form-control" placeholder="e.g. Weekly budget release" />
                            </div>
                            <div class="col-12">
                                <label class="form-label">Reference No <span class="text-muted">(optional)</span></label>
                                <input v-model="pcTxnForm.reference_number" type="text" class="form-control" placeholder="Voucher / transfer ref" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="acct-btn-secondary" @click="pcTxnModal.open = false">Cancel</button>
                        <button class="acct-btn-primary" :disabled="pcTxnSaving" @click="submitTransaction">
                            <span v-if="pcTxnSaving"><i class="ri-loader-4-line spin"></i> Saving...</span>
                            <span v-else>Replenish Fund</span>
                        </button>
                    </div>
                </div>
            </div>

        </template>

    </div>
</template>

<script>
import axios from "axios";
import { router } from "@inertiajs/vue3";
import MainLayout from "@/Shared/Layouts/Main.vue";
import AccountingLayout from "@/Pages/Modules/Accounting/AccountingLayout.vue";

const emptyFtForm = () => ({
    transfer_date: new Date().toISOString().slice(0, 10),
    from_bank_account_id: '',
    to_bank_account_id: '',
    amount: '',
    reference_number: '',
    notes: '',
});

const emptyBdForm = () => ({
    deposit_date: new Date().toISOString().slice(0, 10),
    cash_account_id: '',
    bank_account_id: '',
    amount: '',
    reference: '',
    notes: '',
});

const emptyPcTxnForm = (type = 'replenishment') => ({
    type,
    transaction_date: new Date().toISOString().slice(0, 10),
    amount: '',
    category: '',
    description: '',
    reference_number: '',
    source_type: 'cash',
    bank_account_id: '',
    receipt: null,
});

export default {
    layout: [MainLayout, AccountingLayout],
    props: {
        transfers:    { type: Array,  default: () => [] },
        funds:        { type: Array,  default: () => [] },
        bankAccounts: { type: Array,  default: () => [] },
        cashAccounts: { type: Array,  default: () => [] },
        deposits:     { type: Array,  default: () => [] },
        cashPosition: { type: Object, default: () => ({ data_ready: false, bank_balances: [], petty_cash: [], total_bank: 0, total_petty_cash: 0, total_cash: 0 }) },
        summaryCards: { type: Array,  default: () => [] },
        stats:        { type: Object, default: () => ({}) },
    },
    data() {
        return {
            activeTab: 'cash_position',
            selectedFundId: this.funds[0]?.id ?? null,

            // Fund transfer form state
            ftModal:  { open: false },
            ftForm:   emptyFtForm(),
            ftErrors: {},
            ftSaving: false,

            // Petty cash fund creation
            pcFundModal:  { open: false },
            pcFundForm:   { name: '', gl_code: '', initial_balance: '', weekly_budget: '' },
            pcFundErrors: {},
            pcFundSaving: false,

            // Petty cash transaction
            pcTxnModal:  { open: false },
            pcTxnForm:   emptyPcTxnForm(),
            pcTxnErrors: {},
            pcTxnSaving: false,

            // Bank deposits
            bdModal:  { open: false },
            bdForm:   emptyBdForm(),
            bdErrors: {},
            bdSaving: false,

            // Fund edit modal
            fundEditModal:  { open: false },
            fundEditForm:   { name: '', weekly_budget: '' },
            fundEditSaving: false,

            // Approved replenishment requests for the replenish modal
            repRequests:        [],
            repRequestsLoading: false,
            selectedRepRequest: '',
        };
    },
    computed: {
        selectedFund() {
            return this.funds.find(f => f.id === this.selectedFundId) ?? null;
        },
        weeklySpent() {
            return this.selectedFund?.weekly_spent ?? 0;
        },
        weeklyRemaining() {
            return this.selectedFund?.weekly_remaining ?? null;
        },
        weeklyBarStyle() {
            if (!this.selectedFund || !this.selectedFund.weekly_budget) return {};
            const pct = Math.min((this.weeklySpent / this.selectedFund.weekly_budget) * 100, 100);
            const color = pct >= 100 ? '#ef4444' : pct >= 75 ? '#f59e0b' : '#22c55e';
            return { width: pct + '%', background: color };
        },
    },
    methods: {
        formatCurrency(value) {
            return '₱' + Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        // ── Fund Transfers ────────────────────────────────────────────
        openCreateTransfer() {
            this.ftForm   = emptyFtForm();
            this.ftErrors = {};
            this.ftModal.open = true;
        },
        async submitTransfer() {
            this.ftSaving = true;
            this.ftErrors = {};
            try {
                await axios.post('/accounting/fund-transfers', this.ftForm);
                this.ftModal.open = false;
                router.reload({ preserveScroll: true });
            } catch (e) {
                if (e.response?.status === 422) this.ftErrors = e.response.data.errors || {};
            } finally {
                this.ftSaving = false;
            }
        },
        async confirmDeleteTransfer(t) {
            if (!confirm(`Delete transfer ${t.transfer_no} (${t.amount_formatted})? The journal entry will be reversed.`)) return;
            await axios.delete(`/accounting/fund-transfers/${t.id}`);
            router.reload({ preserveScroll: true });
        },

        // ── Petty Cash ────────────────────────────────────────────────
        openCreateFund() {
            this.pcFundForm   = { name: '', gl_code: '', initial_balance: '', weekly_budget: '' };
            this.pcFundErrors = {};
            this.pcFundModal.open = true;
        },
        async submitFund() {
            this.pcFundSaving = true;
            this.pcFundErrors = {};
            try {
                const res = await axios.post('/accounting/petty-cash/funds', this.pcFundForm);
                this.pcFundModal.open = false;
                router.reload({ preserveScroll: true });
            } catch (e) {
                if (e.response?.status === 422) this.pcFundErrors = e.response.data.errors || {};
            } finally {
                this.pcFundSaving = false;
            }
        },
        async openAddTransaction(type) {
            this.pcTxnForm        = emptyPcTxnForm(type);
            this.pcTxnErrors      = {};
            this.selectedRepRequest = '';
            this.repRequests      = [];
            this.pcTxnModal.open  = true;
            this.$nextTick(() => {
                if (this.$refs.pcReceiptInput) this.$refs.pcReceiptInput.value = '';
            });

            if (type === 'replenishment' && this.selectedFundId) {
                this.repRequestsLoading = true;
                try {
                    const { data } = await axios.get('/replenishments', {
                        params: { fund_id: this.selectedFundId, status: 'approved', count: 100 },
                    });
                    this.repRequests = data.data ?? [];
                } catch (e) {
                    this.repRequests = [];
                } finally {
                    this.repRequestsLoading = false;
                }
            }
        },
        selectRepRequest() {
            const req = this.repRequests.find(r => r.id == this.selectedRepRequest);
            if (!req) return;
            this.pcTxnForm.amount      = req.total_amount;
            this.pcTxnForm.description = `Replenishment for ${req.reference_no}`;
        },
        onPcReceiptChange(e) {
            this.pcTxnForm.receipt = e.target.files[0] || null;
        },
        async submitTransaction() {
            this.pcTxnSaving = true;
            this.pcTxnErrors = {};
            try {
                const base = {
                    ...this.pcTxnForm,
                    fund_id: this.selectedFundId,
                    replenishment_request_id: this.selectedRepRequest || null,
                };
                const payload = this.pcTxnForm.receipt
                    ? (() => {
                        const fd = new FormData();
                        Object.entries(base).forEach(([k, v]) => {
                            if (k === 'receipt' && v instanceof File) fd.append('receipt', v);
                            else if (v !== null && v !== undefined) fd.append(k, v);
                        });
                        return fd;
                    })()
                    : base;

                const headers = this.pcTxnForm.receipt ? { 'Content-Type': 'multipart/form-data' } : {};
                await axios.post('/accounting/petty-cash/transactions', payload, { headers });
                this.pcTxnModal.open = false;
                router.reload({ preserveScroll: true });
            } catch (e) {
                if (e.response?.status === 422) this.pcTxnErrors = e.response.data.errors || {};
            } finally {
                this.pcTxnSaving = false;
            }
        },
        async confirmDeleteTransaction(t) {
            if (!confirm(`Delete transaction ${t.transaction_no}? The journal entry will be reversed and the fund balance will be adjusted.`)) return;
            await axios.delete(`/accounting/petty-cash/transactions/${t.id}`);
            router.reload({ preserveScroll: true });
        },

        // ── Bank Deposits ─────────────────────────────────────────────
        openCreateDeposit() {
            this.bdForm   = emptyBdForm();
            this.bdErrors = {};
            this.bdModal.open = true;
        },
        async submitDeposit() {
            this.bdSaving = true;
            this.bdErrors = {};
            try {
                await axios.post('/accounting/bank-deposits', this.bdForm);
                this.bdModal.open = false;
                router.reload({ preserveScroll: true });
            } catch (e) {
                if (e.response?.status === 422) this.bdErrors = e.response.data.errors || {};
            } finally {
                this.bdSaving = false;
            }
        },
        async confirmDeleteDeposit(d) {
            if (!confirm(`Delete deposit ${d.deposit_no} (${d.amount_formatted})? The journal entry will be reversed.`)) return;
            await axios.delete(`/accounting/bank-deposits/${d.id}`);
            router.reload({ preserveScroll: true });
        },

        // ── Fund Edit ─────────────────────────────────────────────────
        openEditFund(fund) {
            this.fundEditForm = { name: fund.name, weekly_budget: fund.weekly_budget || '' };
            this.fundEditModal.open = true;
        },
        async submitEditFund() {
            this.fundEditSaving = true;
            try {
                await axios.put(`/accounting/petty-cash/funds/${this.selectedFundId}`, this.fundEditForm);
                this.fundEditModal.open = false;
                router.reload({ preserveScroll: true });
            } catch (e) {
                // validation errors not expected here; reload will re-sync
            } finally {
                this.fundEditSaving = false;
            }
        },
    },
};
</script>

<style scoped>
/* ── Cash Position ───────────────────────────────────────────── */
.cash-total-card {
    background: linear-gradient(135deg, #1a4d3d 0%, #2d7a5f 100%);
    border-radius: 14px;
    padding: 1.5rem 2rem;
    color: #fff;
}
.cash-total-label  { font-size: 0.78rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.07em; opacity: 0.8; }
.cash-total-value  { font-size: 2.2rem; font-weight: 800; color: #fff; }
.cash-sub-label    { font-size: 0.72rem; font-weight: 600; opacity: 0.7; text-transform: uppercase; letter-spacing: 0.05em; }
.cash-sub-value    { font-size: 1.1rem; font-weight: 700; color: #a8e6cf; }
.gl-status-chip    { display: inline-flex; align-items: center; padding: 2px 10px; border-radius: 999px; font-size: 0.72rem; font-weight: 700; }
.gl-status-chip.mapped   { background: #dcfce7; color: #166534; }
.gl-status-chip.unmapped { background: #fef3c7; color: #92400e; }
.total-row td { background: #f4faf8; font-size: 0.85rem; }

/* ── Tab bar ─────────────────────────────────────────────────── */
.cm-tab-bar {
    display: flex;
    gap: 0.5rem;
    border-bottom: 2px solid #e2ece9;
    padding-bottom: 0;
}
.cm-tab-btn {
    padding: 0.5rem 1.1rem;
    border: none;
    border-bottom: 2px solid transparent;
    background: transparent;
    color: #6b8c85;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    margin-bottom: -2px;
    border-radius: 6px 6px 0 0;
    transition: color 0.15s, border-color 0.15s;
}
.cm-tab-btn:hover  { color: #3d8d7a; }
.cm-tab-btn.active { color: #3d8d7a; border-bottom-color: #3d8d7a; background: #f4faf8; }

/* ── Table ───────────────────────────────────────────────────── */
.cm-table thead th {
    background: #edf5f2;
    color: #527267;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    white-space: nowrap;
}
.cm-table tbody td { font-size: 0.85rem; vertical-align: middle; }

/* ── Bank chip ───────────────────────────────────────────────── */
.bank-chip {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 0.78rem;
    font-weight: 600;
    white-space: nowrap;
}
.bank-chip.from { background: #fff0e9; color: #9a3b1b; border: 1px solid #f9c5a8; }
.bank-chip.to   { background: #e9f5f0; color: #1b6b4a; border: 1px solid #a8dcc8; }

/* ── Transaction type chip ───────────────────────────────────── */
.txn-type-chip {
    display: inline-flex;
    align-items: center;
    padding: 2px 10px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
}
.txn-type-chip.replenishment { background: #dcfce7; color: #166534; }
.txn-type-chip.disbursement  { background: #fee2e2; color: #991b1b; }

/* ── Fund balance card ───────────────────────────────────────── */
.fund-balance-card {
    background: linear-gradient(135deg, #f0faf6 0%, #e4f5ee 100%);
    border: 1px solid #c4dfd5;
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
}
.fund-balance-icon {
    width: 48px; height: 48px;
    border-radius: 12px;
    background: rgba(61,141,122,0.12);
    color: #3d8d7a;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}
.fund-name  { font-size: 1rem; font-weight: 700; color: #1a3830; }
.fund-gl    { font-size: 0.78rem; color: #6b8c85; font-family: monospace; }
.fund-balance-label { font-size: 0.75rem; color: #6b8c85; text-transform: uppercase; letter-spacing: 0.05em; }
.fund-balance-value { font-size: 1.6rem; font-weight: 800; color: #1a4d3d; }

/* ── Actions ─────────────────────────────────────────────────── */
.action-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 30px; height: 30px; border-radius: 8px; border: 1px solid;
    cursor: pointer; font-size: 0.9rem; background: transparent; transition: all 0.15s;
}
.action-btn.delete       { border-color: #fca5a5; color: #991b1b; }
.action-btn.delete:hover { background: #fee2e2; }
.action-btn.view         { border-color: #a8d5c5; color: #1a6b4a; text-decoration: none; }
.action-btn.view:hover   { background: #e9f5f0; }

/* ── acct-btn-danger ─────────────────────────────────────────── */
.acct-btn-danger {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.45rem 1rem; border-radius: 8px; border: none;
    background: #dc2626; color: #fff;
    font-size: 0.82rem; font-weight: 700; cursor: pointer; transition: background 0.2s;
}
.acct-btn-danger:hover:not(:disabled) { background: #b91c1c; }
.acct-btn-danger:disabled { opacity: 0.5; cursor: not-allowed; }

/* ── Empty state ─────────────────────────────────────────────── */
.cm-empty-state {
    padding: 2.5rem;
    text-align: center;
    color: #648b74;
}
.cm-empty-state i {
    font-size: 2.5rem; color: #3d8d7a;
    display: block; margin-bottom: 0.75rem;
}

/* ── Modal misc ──────────────────────────────────────────────── */
.modal-subtitle { font-size: 0.82rem; color: #6b8c85; margin: 0; }
.error-msg      { font-size: 0.78rem; color: #dc2626; margin-top: 3px; }

.spin { display: inline-block; animation: spin 0.8s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

/* ── Bank Deposits ───────────────────────────────────────────── */
.deposit-no     { font-size: 0.78rem; font-weight: 700; color: #2f6b5c; font-family: monospace; }
.deposit-amount { color: #1e4d8c; }

/* ── Fund edit button ────────────────────────────────────────── */
.fund-edit-btn {
    display: inline-flex; align-items: center; gap: 0.2rem;
    padding: 1px 8px; border-radius: 6px;
    background: rgba(61,141,122,0.15); border: 1px solid rgba(61,141,122,0.3);
    color: #1a4d3d; font-size: 0.72rem; font-weight: 600;
    cursor: pointer; line-height: 1.6; vertical-align: middle;
}
.fund-edit-btn:hover { background: rgba(61,141,122,0.28); }

/* ── Weekly budget progress bar ──────────────────────────────── */
.week-budget-bar {
    height: 8px; border-radius: 999px;
    background: #c4dfd5;
    overflow: hidden;
}
.week-budget-fill {
    height: 100%; border-radius: 999px;
    transition: width 0.4s ease, background 0.3s;
}
</style>
