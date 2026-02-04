<template>
  <!-- Employee Selector Modal -->
  <div v-if="show" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5)">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">

        <div class="modal-header">
          <h5 class="modal-title">Select Employees</h5>
          <button class="btn-close" @click="$emit('close')" />
        </div>

        <div class="modal-body">
          <!-- Search and Filter -->
          <div class="row mb-3">
            <div class="col-md-6">
              <input
                type="text"
                v-model="searchQuery"
                class="form-control"
                placeholder="Search employees..."
              >
            </div>
            <div class="col-md-6">
              <select v-model="positionFilter" class="form-control">
                <option value="">All Positions</option>
                <option v-for="position in uniquePositions" :key="position" :value="position">
                  {{ position }}
                </option>
              </select>
            </div>
          </div>

          <table class="table table-sm">
            <thead>
              <tr>
                <th>
                  <input
                    type="checkbox"
                    :checked="isAllSelected"
                    :indeterminate="isIndeterminate"
                    @change="toggleSelectAll"
                  >
                </th>
                <th>Employee</th>
                <th>Position</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="employee in filteredEmployees"
                :key="employee.value"
                :class="{ 'table-active': localSelected.includes(employee.value) }"
                @click="toggleEmployee(employee.value)"
                style="cursor: pointer;"
              >
                <td>
                  <input
                    type="checkbox"
                    :checked="localSelected.includes(employee.value)"
                    @change.stop
                    readonly
                  >
                </td>
                <td>{{ employee.name }}</td>
                <td>{{ employee.position_name }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary" @click="$emit('close')">Cancel</button>
          <button class="btn btn-primary" @click="$emit('add', [...localSelected])">Add Selected</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { computed } from 'vue'

export default {
  props: {
    show: Boolean,
    employees: Array,
    selectedEmployeeIds: Array,
    selectedEmployees: Array
  },

  emits: ['close', 'add', 'update:selectedEmployeeIds'],

  data() {
    return {
      localSelected: [],
      searchQuery: '',
      positionFilter: ''
    }
  },

  watch: {
    selectedEmployeeIds: {
      immediate: true,
      handler(newVal) {
        this.localSelected = [...newVal]
      }
    }
  },

  computed: {
    uniquePositions() {
      return [...new Set(this.employees.map(emp => emp.position_name))]
    },
    filteredEmployees() {
      let filtered = this.employees
      if (this.selectedEmployees && this.selectedEmployees.length > 0) {
        const selectedValues = this.selectedEmployees.map(emp => emp.value)
        filtered = filtered.filter(emp => !selectedValues.includes(emp.value))
      }
      if (this.searchQuery) {
        filtered = filtered.filter(emp => emp.name.toLowerCase().includes(this.searchQuery.toLowerCase()))
      }
      if (this.positionFilter) {
        filtered = filtered.filter(emp => emp.position_name === this.positionFilter)
      }
      return filtered
    },
    isAllSelected() {
      return this.filteredEmployees.length > 0 && this.filteredEmployees.every(emp => this.localSelected.includes(emp.value))
    },
    isIndeterminate() {
      const selectedInFiltered = this.filteredEmployees.filter(emp => this.localSelected.includes(emp.value)).length
      return selectedInFiltered > 0 && selectedInFiltered < this.filteredEmployees.length
    }
  },

  methods: {
    handleSelectionChange() {
      this.$emit('update:selectedEmployeeIds', [...this.localSelected])
    },
    toggleEmployee(employeeValue) {
      if (this.localSelected.includes(employeeValue)) {
        this.localSelected = this.localSelected.filter(id => id !== employeeValue)
      } else {
        this.localSelected.push(employeeValue)
      }
      this.handleSelectionChange()
    },
    toggleSelectAll() {
      if (this.isAllSelected) {
        // Deselect all filtered employees
        this.localSelected = this.localSelected.filter(id => !this.filteredEmployees.some(emp => emp.value === id))
      } else {
        // Select all filtered employees
        const filteredValues = this.filteredEmployees.map(emp => emp.value)
        this.localSelected = [...new Set([...this.localSelected, ...filteredValues])]
      }
      this.handleSelectionChange()
    }
  }
}
</script>
