<table cellpadding="0" cellspacing="0" border="0" class="tableGreen" width="200" class="tableBg">
	<tr>
		<td class="tableThGreen" nowrap="nowrap"> <img src="images/folder_network.gif"> {$translate->say('dashboard.mailbox_loads')|capitalize}</td>
	</tr>
	<tr>
		<td>
			<table cellpadding="3" cellspacing="1" border="0" width="200" class="tableBg">
				{foreach from=$mailboxes item=mailbox}
				<tr>
					<td class="tableCellBg"><a href="index.php?c=core.module.dashboard&a=clickmailbox&id={$mailbox->id}"><b>{$mailbox->name}</b></a> (xx)</td>
				</tr>
				{foreachelse}
				<tr>
					<td class="tableCellBg">{$translate->say('dashboard.no_mailboxes')}</td>
				</tr>
				{/foreach}
			</table>
		</td>
	</tr>
</table>
