<script lang="ts">
    import RegistrationForm from './RegistrationForm.svelte';

    let { tournament, divisions, registration } = $props();
</script>

<RegistrationForm {tournament} {divisions} {registration} />
