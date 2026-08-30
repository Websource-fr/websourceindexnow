<div class="panel">
	<h3><i class="icon icon-search"></i> {l s='Websource - Module IndexNow' mod='websourceindexnow'}</h3>
	<p>
		<strong>{l s='IndexNow : l’indexation instantanée de vos pages sur les moteurs de recherche' mod='websourceindexnow'}</strong><br />
		{l s='Ce module vous permet de notifier automatiquement les moteurs de recherche compatibles (Bing, Yandex, Seznam, etc.) à chaque ajout, modification ou suppression de contenu sur votre boutique.' mod='websourceindexnow'}<br />
		<br />
		{l s='Grâce à IndexNow, vos nouvelles pages et vos modifications sont prises en compte en quelques minutes au lieu de plusieurs jours.' mod='websourceindexnow'}
	</p>
	<ul>
		<li>{l s='Générez ou saisissez votre clé IndexNow dans la configuration du module.' mod='websourceindexnow'}</li>
		<li>{l s='Le module crée automatiquement le fichier clé à la racine de votre site.' mod='websourceindexnow'}</li>
		<li>{l s='À chaque changement sur vos produits, catégories ou pages, l’URL concernée est envoyée à IndexNow.' mod='websourceindexnow'}</li>
		<li>{l s='Compatible avec les dernières versions de PrestaShop.' mod='websourceindexnow'}</li>
	</ul>
	<p>
		<em>{l s='Pour plus d’informations sur IndexNow, visitez le site officiel : ' mod='websourceindexnow'}
			<a href="https://www.indexnow.org/" target="_blank">https://www.indexnow.org/</a>
		</em>
	</p>
	<p>
		<em>{l s='Pour obtenir une clé API Indexing de chez Google, veuillez vous rendre ici : ' mod='websourceindexnow'}
			<a href="https://console.cloud.google.com/marketplace/product/google/indexing.googleapis.com" target="_blank">https://console.cloud.google.com/marketplace/product/google/indexing.googleapis.com</a><br />
			{l s='Activez le produit, cliquez ensuite sur Gérer puis générez votre clé api dans l\'onglet "Identifiants" situé à gauche' mod='websourceindexnow'}
		</em>
	</p>
	<p>
		<em>{l s='Pour google le quota est limité à 200 urls, pensez à demander une augmentation de quota : ' mod='websourceindexnow'}
			<a href="https://docs.google.com/forms/d/e/1FAIpQLSc_mpLw3WnnCt3pVbUHYZZ6ZdOS-c0GIj-WZ_k54SG-jDqCXQ/viewform?hl=fr" target="_blank">https://docs.google.com/forms/d/e/1FAIpQLSc_mpLw3WnnCt3pVbUHYZZ6ZdOS-c0GIj-WZ_k54SG-jDqCXQ/viewform?hl=fr</a>
		</em>
	</p>
	<p>
		<em>{l s='Il faut également autoriser l\'URL suivante ' mod='websourceindexnow'} "{$current_url}" {l s='en modifiant la clé dans "Identifiants > ID clients OAuth 2.0".' mod='websourceindexnow'}</em>
	</p>
</div>

{if isset($indexnow_manual_result)}
	<div class="alert alert-info my-3" style="margin-top:10px;">{$indexnow_manual_result}</div>
{/if}

<ul class="nav nav-tabs" id="indexnowTabs">
	<li class="active"><a href="#auto" data-toggle="tab">{l s='Configuration automatique' mod='websourceindexnow'}</a></li>
	<li><a href="#manual" data-toggle="tab">{l s='Envoi manuel' mod='websourceindexnow'}</a></li>
</ul>
<div class="tab-content">
	<div class="tab-pane active" id="auto">
		{if isset($form)}
			{$form|escape:'html':'UTF-8'}
		{/if}
	</div>
	<div class="tab-pane" id="manual">
		<form method="post" action="">
			<div class="form-group">
				<label>{l s='URL à envoyer à IndexNow' mod='websourceindexnow'}</label>
				<input type="text" name="indexnow_manual_url" class="form-control"
					   placeholder="{$shop_url}page.html"
					   value="{$shop_url}" />
			</div>
			<button type="submit" name="submitIndexnowManual" class="btn btn-primary">
				<i class="icon icon-send"></i> {l s='Envoyer cette URL à IndexNow' mod='websourceindexnow'}
			</button>
		</form>
	</div>
</div>
