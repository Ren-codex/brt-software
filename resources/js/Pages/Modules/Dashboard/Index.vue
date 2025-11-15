<template>
    <Head title="Dashboard"/>
    <PageHeader title="Dashboard" pageTitle="List" />
    <div class="chat-wrapper d-lg-flex gap-1 mx-n4 mt-n4 p-1">
      <div class="file-manager-sidebar">
           <table class="table table-bordered">
                <tbody>
                    <tr>
                        <td style="border-right: none; border-left: none; cursor: pointer;" @click="openInformation()"><span class="fw-semibold fs-12 ms-2">Business Information</span></td>
                    </tr>
                </tbody>
           </table>
      </div>
      <div class="file-manager-content w-100 p-3 py-0">
        
      </div>
      <div class="file-manager-sidebar">

    
      </div>
    </div>
</template>
<script>
import PageHeader from '@/Shared/Components/PageHeader.vue';
export default {
    props: ['employee'],
    components: { PageHeader }
}
</script>