{if isset($success_message)}
    <div class="alert alert-success">{$success_message}</div>
{elseif isset($error_message)}
    <div class="alert alert-danger">{$error_message}</div>
{/if}