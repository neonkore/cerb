<form action="{devblocks_url}{/devblocks_url}" method="POST">
<input type="hidden" name="c" value="kb">
<input type="hidden" name="a" value="saveKbCategoryEditPanel">
<input type="hidden" name="id" value="{$category->id}">
<input type="hidden" name="return" value="{$return}">
<input type="hidden" name="delete_box" value="0">

{if !empty($category)}
<h1>Modify Subcategory</h1>
{else}
<h1>Add Subcategory</h1>
{/if}

<b>Name:</b><br>
<input type="text" name="name" value="{$category->name|escape}" style="width:99%;border:solid 1px rgb(180,180,180);"><br>
<br>

{if 0 && !empty($category)}
<b>Parent Category:</b><br>
<div style="overflow:auto;height:150px;border:solid 1px rgb(180,180,180);background-color:rgb(255,255,255);">
	{foreach from=$levels item=depth key=node_id name=levels}
		<label>
			<input type="radio" name="parent_id" value="{$node_id}" {if (empty($category) && $root_id==$node_id) || $node_id==$category->parent_id}checked{/if}>
			<span style="padding-left:{math equation="(x-1)*10" x=$depth}px;{if !$depth}font-weight:bold;{/if}">{if $depth}<img src="{devblocks_url}c=resource&p=cerberusweb.core&f=images/tree_cap.gif{/devblocks_url}" align="absmiddle">{else}<img src="{devblocks_url}c=resource&p=cerberusweb.core&f=images/folder.gif{/devblocks_url}" align="absmiddle">{/if} <span id="kbTreeCat{$node_id}">{$categories.$node_id->name}</span>
		</label>
		<br>
	{/foreach}
</div>
{elseif !empty($category)}
	<input type="hidden" name="parent_id" value="{$category->parent_id}">
{elseif !empty($root_id)}
	<input type="hidden" name="parent_id" value="{$root_id}">
{/if}

<div id="deleteCategory" style="display:none;">
	<div style="background-color:rgb(255,220,220);border:1px solid rgb(200,50,50);margin:0px;padding:5px;">
		<h3>Delete Category</h3>
		This will remove this category and all its subcategories. Your 
		article content will not be deleted, but articles will be removed  
		from these categories.<br>
		<button type="button" onclick="this.form.delete_box.value='1';this.form.submit();">Delete</button>
		<button type="button" onclick="this.form.delete_box.value='0';toggleDiv('deleteCategory','none');">Cancel</button>
	</div>
	<br>
</div>

<button type="submit"><img src="{devblocks_url}c=resource&p=cerberusweb.core&f=images/check.gif{/devblocks_url}" align="top"> {$translate->_('common.save_changes')|capitalize}</button>
{if !empty($category)}<button type="button" onclick="toggleDiv('deleteCategory','block');"><img src="{devblocks_url}c=resource&p=cerberusweb.core&f=images/delete2.gif{/devblocks_url}" align="top"> {$translate->_('common.remove')|capitalize}</button>{/if}
<button type="button" onclick="genericPanel.hide();"><img src="{devblocks_url}c=resource&p=cerberusweb.core&f=images/delete.gif{/devblocks_url}" align="top"> {$translate->_('common.close')|capitalize}</button>

</form>