{if $status == 'ok'}
	<p class="success">
		{l s='Your order has been completed.' mod='cashfree'}
		<br /><br />{l s='For any questions or for further information, please contact our' mod='cashfree'} <a href="{$link->getPageLink('contact', true)|escape:'html'}">{l s='customer support' mod='cashfree'}</a>.
	</p>
{else}
	<p class="warning">
		{l s='We noticed a problem with your order. If you think this is an error, feel free to contact our' mod='cashfree'} 
		<a href="{$link->getPageLink('contact', true)|escape:'html'}">{l s='customer support' mod='cashfree'}</a>.
	</p>
{/if}

