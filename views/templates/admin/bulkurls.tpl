<div class="panel">
    <h3>Envoi en masse d'URLs à IndexNow</h3>
    <form method="get" action="{$send_url_action}">
        <input type="hidden" name="token" value="{$admin_token}" />
        <label for="type">Type d'URL :</label>
        <select name="type" id="type" onchange="this.form.submit();">
            {foreach from=$types key=key item=label}
                <option value="{$key}" {if $current_type == $key}selected{/if}>{$label}</option>
            {/foreach}
        </select>
        <input type="hidden" name="controller" value="AdminWebsourceindexnowBulkurls" />
    </form>
    <form method="post" action="{$send_url_action}">
        <table class="table">
            <thead>
            <tr>
                <th><input type="checkbox" id="checkAll" onclick="$('.cb-url').prop('checked', this.checked);" /></th>
                <th>ID</th>
                <th>URL</th>
            </tr>
            </thead>
            <tbody>
            {foreach from=$urls item=url}
                <tr>
                    <td><input type="checkbox" class="cb-url" name="urls_to_send[]" value="{$url.url|escape:'html'}" checked /></td>
                    <td>{$url.id}</td>
                    <td><a href="{$url.url|escape:'html'}" target="_blank">{$url.url|escape:'html'}</a></td>
                </tr>
            {/foreach}
            </tbody>
        </table>
        <button type="submit" name="submitBulkSendUrls" class="btn btn-primary">
            <i class="icon icon-send"></i> Envoyer les URLs cochées à IndexNow
        </button>
    </form>
</div>
