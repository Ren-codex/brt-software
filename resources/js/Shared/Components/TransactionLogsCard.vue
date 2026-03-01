<template>
  <div class="library-card loan-logs-card" :class="{ 'is-compact': compact }">
    <div class="library-card-header">
      <div class="d-flex align-items-center gap-3">
        <div class="header-icon">
          <i class="ri-history-line"></i>
        </div>
        <div>
          <h4 class="header-title mb-1">{{ title }}</h4>
          <p class="header-subtitle mb-0">{{ subtitle }}</p>
        </div>
      </div>
      <div class="header-badge" v-if="normalizedLogs.length">
        <span class="badge-count">{{ normalizedLogs.length }}</span>
        <span>events</span>
      </div>
    </div>

    <div class="library-card-body p-0">
      <div v-if="normalizedLogs.length" class="timeline-container">
        <div class="timeline mt-4">
          <div
            v-for="(log, index) in displayedLogs"
            :key="log.id || index"
            class="loan-log-item"
          >
            <div class="timeline-year">{{ log.created_at }}</div>
            <div class="timeline-marker">
              <div class="marker-dot">
                <i :class="getActionIcon(log.action)"></i>
              </div>
            </div>

            <div class="timeline-content">
              <p class="log-title">{{ capitalizeFirstLetter(log.action || 'Transaction') }}</p>
              <div class="log-actor">
                <span class="actor-avatar">
                  <i class="ri-user-line"></i>
                </span>
                <span class="actor-name">{{ log.actioned_by || 'System' }}</span>
              </div>
              <p class="log-description">{{ getLogDescription(log) }}</p>
            </div>
          </div>
        </div>

        <div class="timeline-footer" v-if="hasMore">
          <button class="btn-load-more" @click="loadMoreLogs">
            <i class="ri-arrow-down-line"></i>
            Show More
          </button>
        </div>
      </div>

      <div v-else class="empty-state">
        <div class="empty-icon">
          <i class="ri-history-line"></i>
        </div>
        <h5 class="empty-title">No Transaction Logs</h5>
        <p class="empty-text">Activity history will appear here once transactions are made.</p>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'TransactionLogsCard',
  props: {
    logs: {
      type: Array,
      default: () => []
    },
    title: {
      type: String,
      default: 'Transaction Logs'
    },
    subtitle: {
      type: String,
      default: 'Activity history and remarks'
    },
    compact: {
      type: Boolean,
      default: false
    },
    initialVisible: {
      type: Number,
      default: 4
    },
    logsPerPage: {
      type: Number,
      default: 5
    }
  },
  data() {
    return {
      visibleLogs: this.initialVisible
    };
  },
  computed: {
    normalizedLogs() {
      return Array.isArray(this.logs) ? this.logs : [];
    },
    sortedLogs() {
      return [...this.normalizedLogs].sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
    },
    displayedLogs() {
      return this.sortedLogs.slice(0, this.visibleLogs);
    },
    hasMore() {
      return this.sortedLogs.length > this.visibleLogs;
    }
  },
  watch: {
    logs() {
      this.visibleLogs = this.initialVisible;
    }
  },
  methods: {
    capitalizeFirstLetter(value) {
      if (!value) return '';
      const text = String(value);
      return text.charAt(0).toUpperCase() + text.slice(1);
    },
    getActionIcon(action) {
      const actionLower = (action || '').toLowerCase();
      if (actionLower.includes('create')) return 'ri-add-line';
      if (actionLower.includes('update') || actionLower.includes('edit')) return 'ri-pencil-line';
      if (actionLower.includes('approve')) return 'ri-check-line';
      if (actionLower.includes('reject') || actionLower.includes('deny')) return 'ri-close-line';
      if (actionLower.includes('delete')) return 'ri-delete-bin-line';
      if (actionLower.includes('payment')) return 'ri-bank-card-line';
      if (actionLower.includes('login')) return 'ri-login-box-line';
      return 'ri-information-line';
    },
    getLogDescription(log) {
      if (log.remarks) return log.remarks;
      if (log.actioned_by) return `${log.action || 'Request'} by ${log.actioned_by}.`;
      return 'System generated activity.';
    },
    loadMoreLogs() {
      this.visibleLogs += this.logsPerPage;
    }
  }
};
</script>

<style scoped>
.library-card {
  background: #fff;
  border-radius: 20px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
  overflow: hidden;
  transition: all 0.3s ease;
}

.library-card:hover {
  box-shadow: 0 15px 50px rgba(0, 0, 0, 0.12);
}

.header-icon {
  width: 48px;
  height: 48px;
  background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  color: #667eea;
}

.header-title {
  font-size: 18px;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.header-subtitle {
  font-size: 14px;
  color: #64748b;
}

.header-badge {
  padding: 8px 16px;
  border-radius: 40px;
  font-size: 14px;
  color: #475569;
  margin-left: 30px;
}

.badge-count {
  font-weight: 600;
  color: #667eea;
  margin-right: 4px;
}

.timeline-container {
  position: relative;
  padding: 20px 0;
  background: #f3f4f6;
}

.timeline {
  padding: 4px 20px 0;
}

.loan-log-item {
  display: grid;
  grid-template-columns: 130px 20px 1fr;
  gap: 10px;
  position: relative;
  margin-bottom: 14px;
  align-items: start;
  width: 100%;
  animation: slideIn 0.3s ease forwards;
  opacity: 0;
  transform: translateX(-10px);
}

.loan-log-item:last-child {
  margin-bottom: 0;
}

.timeline-year {
  font-size: 13px;
  color: #6b7280;
  line-height: 1.35;
  text-align: right;
  padding-top: 1px;
  word-break: break-word;
}

.timeline-marker {
  position: relative;
  display: flex;
  justify-content: center;
  align-items: flex-start;
  min-height: 20px;
}

.loan-log-item:not(:last-child) .timeline-marker::after {
  content: '';
  position: absolute;
  top: 20px;
  left: 9px;
  width: 2px;
  height: calc(100% + 8px);
  background: #d1d5db;
  border-radius: 99px;
}

.marker-dot {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #047b62;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 10px;
  z-index: 2;
}

.timeline-content {
  padding-top: 0;
}

.log-title {
  margin: 0;
  color: #334155;
  font-size: 14px;
  font-weight: 500;
  line-height: 1.35;
}

.log-actor {
  margin-top: 3px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.actor-avatar {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: #d1d5db;
  color: #4b5563;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
}

.actor-name {
  color: #4b5563;
  font-size: 12px;
  line-height: 1.3;
}

.log-description {
  margin: 2px 0 0;
  color: #6b7280;
  font-size: 13px;
  line-height: 1.4;
  background-color: white;
  padding: 10px;
  border-radius: 12px;
  margin-right: 10px;
}

.timeline-footer {
  padding: 20px 24px 16px;
  text-align: center;
  border-top: 1px solid #f1f5f9;
  margin-top: 8px;
}

.btn-load-more {
  background: none;
  border: 2px solid #e2e8f0;
  padding: 10px 24px;
  border-radius: 40px;
  font-size: 14px;
  font-weight: 500;
  color: #475569;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-load-more:hover {
  background: #f8fafc;
  border-color: #cbd5e1;
  color: #1e293b;
  transform: translateY(-2px);
}

.btn-load-more i {
  transition: transform 0.2s ease;
}

.btn-load-more:hover i {
  transform: translateY(2px);
}

.empty-state {
  padding: 60px 24px;
  text-align: center;
}

.empty-icon {
  width: 80px;
  height: 80px;
  background: #f8fafc;
  border-radius: 80px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 20px;
  font-size: 40px;
  color: #94a3b8;
  border: 2px dashed #e2e8f0;
}

.empty-title {
  font-size: 18px;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 8px;
}

.empty-text {
  font-size: 14px;
  color: #64748b;
  max-width: 300px;
  margin: 0 auto;
}

@media (max-width: 768px) {
  .timeline {
    padding: 4px 12px 0;
  }

  .loan-log-item {
    grid-template-columns: 104px 18px 1fr;
    gap: 8px;
  }
}

@keyframes slideIn {
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

.loan-log-item:nth-child(1) { animation-delay: 0.05s; }
.loan-log-item:nth-child(2) { animation-delay: 0.1s; }
.loan-log-item:nth-child(3) { animation-delay: 0.15s; }
.loan-log-item:nth-child(4) { animation-delay: 0.2s; }
.loan-log-item:nth-child(5) { animation-delay: 0.25s; }
.loan-log-item:nth-child(6) { animation-delay: 0.3s; }
.loan-log-item:nth-child(7) { animation-delay: 0.35s; }
.loan-log-item:nth-child(8) { animation-delay: 0.4s; }
.loan-log-item:nth-child(9) { animation-delay: 0.45s; }
.loan-log-item:nth-child(10) { animation-delay: 0.5s; }

.loan-logs-card.is-compact {
  border-radius: 12px;
}

.loan-logs-card.is-compact .library-card-header {
  padding: 10px 10px 8px;
  align-items: flex-start;
  gap: 6px;
}

.loan-logs-card.is-compact .header-icon {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  font-size: 14px;
}

.loan-logs-card.is-compact .header-title {
  font-size: 16px;
  line-height: 1.2;
}

.loan-logs-card.is-compact .header-subtitle {
  display: none;
}

.loan-logs-card.is-compact .library-card-header .gap-3 {
  gap: 0.5rem !important;
}

.loan-logs-card.is-compact .header-badge {
  padding: 2px 8px;
  border-radius: 10px;
  font-size: 10px;
  white-space: nowrap;
}

.loan-logs-card.is-compact .timeline-container {
  padding: 8px 0;
}

.loan-logs-card.is-compact .timeline {
  padding: 0 10px;
}

.loan-logs-card.is-compact .loan-log-item {
  grid-template-columns: 92px 16px 1fr;
  gap: 7px;
  margin-bottom: 8px;
}

.loan-logs-card.is-compact .timeline-year {
  font-size: 11px;
}

.loan-logs-card.is-compact .marker-dot {
  width: 14px;
  height: 14px; 
  font-size: 8px;
}

.loan-logs-card.is-compact .loan-log-item:not(:last-child) .timeline-marker::after {
  left: 6px;
  top: 16px;
}

.loan-logs-card.is-compact .log-title {
  font-size: 12px;
}

.loan-logs-card.is-compact .log-actor {
  gap: 4px;
}

.loan-logs-card.is-compact .actor-avatar {
  width: 14px;
  height: 14px;
  font-size: 9px;
}

.loan-logs-card.is-compact .actor-name {
  font-size: 10px;
}

.loan-logs-card.is-compact .log-description {
  font-size: 11px;
  margin-top: 1px;
}

.loan-logs-card.is-compact .timeline-footer {
  padding: 8px 10px;
}

.loan-logs-card.is-compact .btn-load-more {
  padding: 5px 10px;
  border-width: 1px;
  font-size: 11px;
  gap: 4px;
}

.loan-logs-card.is-compact .empty-state {
  padding: 20px 10px;
}

.loan-logs-card.is-compact .empty-icon {
  width: 40px;
  height: 40px;
  margin-bottom: 8px;
  font-size: 18px;
}

.loan-logs-card.is-compact .empty-title {
  font-size: 13px;
  margin-bottom: 4px;
}

.loan-logs-card.is-compact .empty-text {
  font-size: 11px;
}
</style>
