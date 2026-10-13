<script lang="ts">
    import RegistrationForm from './RegistrationForm.svelte';

    let { tournament, divisions } = $props();
</script>

<RegistrationForm {tournament} {divisions} />
