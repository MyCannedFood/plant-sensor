<template>
    <div class="state">
        <h1 class="view-title">Page not found</h1>
        <p>This address does not match any page in the dashboard.</p>
        <RouterLink :to="{ name: 'overview' }" class="btn-ghost">Back to the overview</RouterLink>
    </div>
</template>
