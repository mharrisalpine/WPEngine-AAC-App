export const MEMBER_TRANSACTIONS_STORAGE_KEY = 'aac_member_transactions_v1';

const TRANSACTIONS_CHANGED = 'aac-member-transactions-changed';

const readJson = (key, fallback) => {
  try {
    const raw = localStorage.getItem(key);
    return raw ? JSON.parse(raw) : fallback;
  } catch (_error) {
    return fallback;
  }
};

const writeJson = (key, value) => {
  localStorage.setItem(key, JSON.stringify(value));
};

const getAllTransactions = () => readJson(MEMBER_TRANSACTIONS_STORAGE_KEY, []);

const dispatchTransactionsChanged = () => {
  if (typeof window === 'undefined') {
    return;
  }
  window.dispatchEvent(new CustomEvent(TRANSACTIONS_CHANGED));
};

const makeTransactionId = () => `txn_${Math.random().toString(36).slice(2, 10)}`;

/**
 * Real transactions only (no demo seed). Excludes legacy seeded rows from older builds.
 */
export const listMemberTransactions = (memberId) => {
  if (!memberId) return [];

  return getAllTransactions()
    .filter((transaction) => transaction.memberId === memberId && !transaction.metadata?.seeded)
    .sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
};

export const subscribeMemberTransactions = (listener) => {
  if (typeof window === 'undefined') {
    return () => {};
  }
  window.addEventListener(TRANSACTIONS_CHANGED, listener);
  return () => window.removeEventListener(TRANSACTIONS_CHANGED, listener);
};

export const recordMemberTransaction = ({
  memberId,
  kind,
  amount,
  description,
  referenceId,
  status = 'Paid',
  metadata = {},
  createdAt,
}) => {
  if (!memberId || !kind || !description) {
    return null;
  }

  const transactions = getAllTransactions();

  if (referenceId) {
    const existing = transactions.find(
      (transaction) => transaction.memberId === memberId && transaction.referenceId === referenceId
    );
    if (existing) {
      return existing;
    }
  }

  const nextTransaction = {
    id: makeTransactionId(),
    memberId,
    kind,
    amount: Number(amount) || 0,
    description,
    referenceId: referenceId || null,
    status,
    metadata,
    createdAt: createdAt || new Date().toISOString(),
  };

  transactions.unshift(nextTransaction);
  writeJson(MEMBER_TRANSACTIONS_STORAGE_KEY, transactions);
  dispatchTransactionsChanged();
  return nextTransaction;
};
