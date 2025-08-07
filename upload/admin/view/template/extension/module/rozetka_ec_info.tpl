	<?php if ($result) { ?>
	<div class="modal-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover">
			<?php if (!empty($result['order_id'])) { ?>
				<tr><td><b><?php echo $text_info_payment_id; ?></b></td><td><?php echo $result['order_id']; ?></td></tr>
			<?php } ?>
			<?php if (!empty($result['text_status'])) { ?>
				<tr><td><b><?php echo $text_info_status; ?></b></td><td><?php echo $result['text_status']; ?></td></tr>
			<?php } ?>
			<?php if (!empty($result['amount'])) { ?>
				<tr><td><b><?php echo $text_info_amount; ?></b></td><td><?php echo $result['amount']; ?></td></tr>
			<?php } ?>
			<?php if (!empty($result['amount_refunded'])) { ?>
				<?php if ($result['amount_final'] > 0) { ?>
				<tr><td><b><?php echo $text_info_amount_final; ?></b></td><td style="color:green;"><b><?php echo $result['amount_final']; ?></b></td></tr>
				<?php } ?>
				<tr><td><b><?php echo $text_info_amount_refunded; ?></b></td><td style="color:red;"><?php echo $result['amount_refunded']; ?></td></tr>
			<?php } ?>
			<?php if (!empty($result['finalAmount'])) { ?>
				<tr><td><b><?php echo $text_info_final_amount; ?></b></td><td><?php echo $result['finalAmount']; ?></td></tr>
			<?php } ?>
			<?php if (!empty($result['currency'])) { ?>
				<tr><td><b><?php echo $text_info_currency; ?></b></td><td><?php echo $result['currency']; ?></td></tr>
			<?php } ?>
			<?php if (!empty($result['createdDate'])) { ?>
				<tr><td><b><?php echo $text_info_create_date; ?></b></td><td><?php echo $result['createdDate']; ?></td></tr>
			<?php } ?>	
			<?php if (!empty($result['modifiedDate'])) { ?>
				<tr><td><b><?php echo $text_info_end_date; ?></b></td><td><?php echo $result['modifiedDate']; ?></td></tr>
			<?php } ?>			
			<?php if (!empty($result['customer'])) { ?>
				<tr><td><b><?php echo $text_info_customer; ?></b></td><td><?php echo $result['customer']; ?></td></tr>
			<?php } ?>
			</table>
		</div>
    </div>
	<?php if ($result['refunded'] == false) { ?>
      <div class="modal-footer">
		<div class="col-sm-6">
			<div class="form-group" style="padding: 0;">
				<span style="display:block;float:left;padding:7px 0;font-size:13px;font-weight:600;color:red;">
					<?php echo $text_write_off; ?>
				</span>
				<input type="text" name="amount_transaction" value="<?php echo $result['amount']; ?>" id="input-amount" class="form-control" style="float:left;max-width:110px;margin-left:10px" />
			</div>
		</div>
        <button type="button" class="btn btn-danger" id="cancel-rc-pay" data-order_id="<?php echo $result['order_id']; ?>"><?php echo $text_cancel_pay; ?></button>
      </div>
	<?php } ?>
	<?php } else { ?>
	<div class="modal-body">
		<?php echo $text_empty; ?>
	</div>
	<?php } ?>
	<script>		
		$('#cancel-rc-pay').on('click', function() {
			var amount = parseFloat($('#input-amount').val());
			var order_id = $(this).attr('data-order_id');

			$.ajax({
				url: 'index.php?route=extension/module/rozetka_ec/paymentRefund&token=<?php echo $token; ?>&amount=' + encodeURIComponent(amount) + '&order_id=' + encodeURIComponent(order_id),
				dataType: 'json',
				beforeSend: function() {
					$('#cancel-rc-pay').button('loading');
				},
				complete: function() {
					$('#cancel-rc-pay').button('reset');
				},
				success: function(json) {
					$('.alert').remove();
					
					if(json['error']) {
						$('.modal-footer').before('<div class="col-sm-12"><div class="alert alert-danger" style="margin-top: 10px;">' + json['error'] + '<button type="button" class="close" data-dismiss="alert">&times;</button></div></div>');
					}
					
					if(json['success']) {
						$('.alert').remove();
						
						$('.container-fluid>.row').before('<div class="alert alert-success">' + json['success'] + '<button type="button" class="close" data-dismiss="alert">&times;</button></div>');
						
						$('#info-modal-rc').modal('hide');
					}	
				}
			});	
		});
	</script>