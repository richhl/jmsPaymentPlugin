<?php use_helper('Number') ?>
Amount: <?php echo format_currency($amount, $currency) ?>
<br/><br/>
<form id="form2_complete_payment" name="form2_complete_payment" method="post" action="<?php echo $url;?>">
    <?php foreach ($dataContainer as $name => $value) : ?>
	<?php if ($value != null) : ?>
	<input type="hidden" id="<?php echo $name;?>" name="<?php echo $name;?>" value="<?php echo $value;?>">
	<?php endif; ?>
    <?php endforeach; ?>
    Please visit <input type="submit" name="<?php echo $url;?>" value="<?php echo $url;?>"> to complete the payment. 
</form>
