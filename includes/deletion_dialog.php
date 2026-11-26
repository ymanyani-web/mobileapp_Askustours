<?php // One dialog shared by the customer and collaborator lists. ?>
<dialog id="user-deletion-dialog" class="panel" style="max-width:560px;width:calc(100% - 32px);max-height:85vh;overflow:auto;border:1px solid #d4dbe3;border-radius:20px">
<h3>Eliminar cuenta</h3><p id="deletion-preview-title"></p><p id="deletion-preview-message" role="status">Cargando los datos asociados…</p><ul id="deletion-preview-counts"></ul>
<form method="post" action="users.php?view=<?=e($view)?>" id="user-deletion-form" class="editor-form">
<input type="hidden" name="_csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id"><input type="hidden" name="revision">
<p>Se guardará una copia recuperable. Las sesiones se cerrarán. Los registros con pagos o liquidaciones se protegen y no se eliminarán.</p>
<label>Motivo<input name="reason" required minlength="5" maxlength="500"></label><label><input type="checkbox" name="confirmation" value="1" required> He revisado los datos y confirmo la eliminación</label>
<div class="filters"><button type="button" id="cancel-user-deletion" class="btn secondary">Cancelar</button><button type="submit" id="confirm-user-deletion" class="btn primary" disabled>Eliminar cuenta</button></div>
</form></dialog><script src="assets/deletion-dialog.js?v=1" defer></script>
