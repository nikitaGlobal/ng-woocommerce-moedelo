<?php
foreach ($args['fields'] as $field) { ?>
    <div class="form-block">
    <label for="<?php echo $field['id'];?>"><?php echo $field['label'];?></label>
    <input id="<?php echo $field['id'];?>" type="<?php echo $field['type']; ?>"
           value="<?php echo $field['value'];?>"
           name="<?php echo $field['name']; ?>" <?php if ($field['required']) { ?>required="required"<?php }

    ?>></div><?php } ?>
