<template>
  <b-modal ref="modal" title="Create Product" @hide="resetForm" hide-footer>
    <form class="customform">
      <div class="mb-3">
        <InputLabel value="Name" :message="form.errors.name"/>
        <TextInput v-model="form.name" type="text" class="form-control" placeholder="Please enter product name" @input="handleInput('name')" />
      </div>

      <div class="mb-3">
        <InputLabel value="Unit" :message="form.errors.unit_id"/>
        <b-form-select v-model="form.unit_id" :options="dropdowns.units" text-field="name" required></b-form-select>
        <div class="invalid-feedback">{{ errors.unit_id }}</div>
      </div>

      <div class="d-flex justify-content-end">
        <b-button @click="hide()" variant="light" block>Cancel</b-button>
        <b-button @click="submit('ok')" variant="primary">
          {{ editMode ? 'Update' : 'Create' }}
        </b-button>
      </div>
    </form>
  </b-modal>
</template>

<script>
import { useForm } from '@inertiajs/vue3';
import Multiselect from "@vueform/multiselect";
import InputLabel from '@/Shared/Components/Forms/InputLabel.vue';
import TextInput from '@/Shared/Components/Forms/TextInput.vue';

export default {
  components: { Multiselect, InputLabel, TextInput },
  props: ['dropdowns'],
  data() {
    return {
      form: useForm({
        id: null,
        name: "",
        unit_id: null,
      }),
      errors: {},
      editable: false,
    };
  },
  methods: {
    show() {
        this.form.reset();
        this.editable = false;
        this.$refs.modal.show();
    },
    edit(data){
        this.form.id = data.id;
        this.form.name = data.name;
        this.form.unit_id = data.unit_id;
        this.editable = true;
        this.$refs.modal.show();
    },
    submit(){
        if(this.editable){
            this.form.put(`/libraries/products/${this.form.id}`,{
                preserveScroll: true,
                onSuccess: (response) => {
                    this.$emit('add', true);
                    this.form.reset();
                    this.hide();
                },
            });
        }else{
            this.form.post('/libraries/products',{
                preserveScroll: true,
                onSuccess: (response) => {
                    this.$emit('add', true);
                    this.form.reset();
                    this.hide();
                },
            });
        }
    },
    handleInput(field) {
        this.form.errors[field] = false;
    },
    hide(){
        this.editable = false;
        this.$refs.modal.hide();
    },
    resetForm(){
        this.form.reset();
        this.errors = {};
        this.editable = false;
    },
  },
};
</script>
