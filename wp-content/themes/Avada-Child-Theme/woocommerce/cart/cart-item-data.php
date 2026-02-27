<?php
/**
 * Cart item data (when outputting non-flat)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/cart/cart-item-data.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see         https://woocommerce.com/document/template-structure/
 * @package     WooCommerce\Templates
 * @version     2.4.0
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<dl class="variation">

	<?php
	 foreach ( $item_data as $data ) : ?>
		<?php 
		
		$str=explode("<br />",wp_kses_post( wpautop( $data['display'] ) ));
		if (str_contains($str[0], 'SNAKES/TESTS')) { 
		$groupedData = [];
				$currentIdentifier = null;

				foreach ($str as $line) {
					if (strpos($line, 'Snake Identifier(User Defined)') !== false) {
						$currentIdentifier = trim(str_replace('Snake Identifier(User Defined)', '', $line));
						$groupedData[$currentIdentifier] = [];
					} elseif ($currentIdentifier !== null) {
						$groupedData[$currentIdentifier][] = $line;
					}
				}
				$i=1;
				 foreach ($groupedData as $identifier => $details): ?>
				 <div class="metacollapsible__wrap">
					<button type="button" class="metacollapsible">SNAKES/TESTS #<?php echo $i;?></button>
					<div class="metacontent">
					<p><span>Snake Identifier</span><span><?php echo htmlspecialchars($identifier); ?></span></p>
						<?php foreach ($details as $detail):
							
							?>
							<?php 
							$d=str_replace('Known Genetics','Known Genetics</span><span>', htmlspecialchars(wp_strip_all_tags($detail)));
							$d=str_replace('</p>','', $d);  
							$d=str_replace('Comment','Comment</span><span>', $d);
							 
							$d=str_replace('Select Test','Select Test</span><span>', $d); 
							// Step 1: Extract the part within the brackets
								// Extract the number at the end
								preg_match('/(\d+)$/', $d, $numberMatch);
								if(isset($numberMatch[0])){
									$number = $numberMatch[0];
								}else{
									$number = '';
								}

								// Remove the number from the string
								$text = preg_replace('/\s*\d+$/', '', $d);

								// Split the remaining string by commas
								$items = explode(',', $text);

								// Trim whitespace from each item
								$items = array_map('trim', $items);
								if($number && count($items)>0){
									$ii=0;
								foreach ($items as $item):
									if ($ii==0) {
										if (str_contains($item, 'Add Secondary Test For Same Shed')) { 
											
										echo ''.str_replace('Add Secondary Test For Same Shed','Add Secondary Test For Same Shed<p><span>', $item).'</span><span>'.$number/count($items).'</span></p>';
										}
										elseif (str_contains($item, 'Pick 3 Tests')) { 
											
											echo ''.str_replace('Pick 3 Tests','3 Tests<p><span>', $item).'</span><span>'.$number/count($items).'</span></p>';
											}
										if (str_contains($item, 'Select Test')) { 
											echo ''.str_replace('Select Test','Select Test<p><span>', $item).'</span><span>'.$number/count($items).'</span></p>';
											}
									}else{
								echo '<p><span>'.$item.'</span><span>'.$number/count($items).'</span></p>';
									}
								$ii++;
								endforeach;
								
								}else{
								echo '<p><span>'.$d.'</span></p>';
								}
							?>
						<?php endforeach; ?>
					</div>
					<?php $i++;?> 
						</div>
				<?php endforeach;?> 
		<?php }else{
		?>
		<dt class="<?php echo sanitize_html_class( 'variation-' . $data['key'] ); ?>"><?php echo wp_kses_post( $data['key'] ); ?>:</dt>
		<dd class="<?php echo sanitize_html_class( 'variation-' . $data['key'] ); ?>"><?php echo wp_kses_post( wpautop( $data['display'] ) ); ?></dd>
	<?php }?>
		<?php endforeach; ?>
</dl>
