<template>
  <div class="audit-table-container">
    <table class="audit-table" v-if="auditData && Object.keys(auditData).length > 0">
      <thead>
        <tr>
          <th>Ability</th>
          <th>Source Type</th>
          <th>Source Name</th>
          <th>Effect Name</th>
          <th>Modification</th>
          <th>Base Value</th>
          <th>New Value</th>
        </tr>
      </thead>
      <tbody>
        <template v-for="(stat, statId) in auditData" :key="statId">
          <tr v-if="stat.audit" v-for="(audit, index) in stat.audit" :key="`${statId}-${index}`">
            <td>{{ stat.name }}</td>
            <td>{{ audit.type }}</td>
            <td>{{ audit.name }}</td>
            <td>{{ audit.effect.name }}</td>
            <td :class="audit.effect.modification === 'positive' ? 'positive-mod' : 'negative-mod'">
              {{ audit.effect.modification === 'positive' ? '+' : '-' }}{{ audit.effect.modifier }}
            </td>
            <td>{{ audit.old }}</td>
            <td>{{ audit.new }}</td>
          </tr>
        </template>
      </tbody>
    </table>
    <div v-else class="empty-state">
      No audit data available for this character
    </div>
  </div>
</template>

<script>
/**
 * AuditTable Component
 * Displays audit trail information for character stats and their modifications
 * 
 * @category Components
 * @package OpenRegister
 * @author OpenRegister Team
 * @copyright 2023 OpenRegister
 * @license AGPL-3.0-or-later
 * @version 1.0.0
 * @link https://github.com/cuzy-app/openregister
 */
export default {
  name: 'AuditTable',
  
  /**
   * Component props
   */
  props: {
    /**
     * Object containing character stats with audit information
     * 
     * @type {Object}
     * @required
     */
    auditData: {
      type: Object,
      required: true,
      default: () => ({})
    }
  }
}
</script>

<style scoped>
.audit-table-container {
  width: 100%;
  overflow-x: auto;
}

.audit-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 10px;
}

.audit-table th, 
.audit-table td {
  padding: 8px;
  text-align: left;
  border-bottom: 1px solid var(--color-border);
}

.audit-table th {
  background-color: var(--color-background-dark);
  font-weight: bold;
}

.audit-table tr:nth-child(even) {
  background-color: var(--color-background-hover);
}

.positive-mod {
  color: green;
  font-weight: bold;
}

.negative-mod {
  color: red;
  font-weight: bold;
}

.empty-state {
  padding: 20px;
  text-align: center;
  color: var(--color-text-maxcontrast);
}
</style>