<?php
/**
 * Job stats
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     wp-job-manager
 * @category    Template
 * @version     2.3.0
 *
 * @var WP_Post $job Array of job post results.
 * @var array   $stats Total stats grouped by section.
 * @var array   $chart Total stats grouped by section.
 */

use WP_Job_Manager\UI\UI_Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>

<div class="jm-job-stats">
	<div class="jm-job-stats-chart">
		<div class="jm-section-header">
			<?php esc_html_e( 'Daily Views', 'wp-job-manager' ); ?>
		</div>
		<div class="jm-chart">
			<?php $values = $chart['values']; ?>
			<div class="jm-chart-y-axis">
				<?php
				foreach ( $chart['y-labels'] as $label ) {
					$position = ( $label / $chart['max'] ) * 100;
					echo '<div class="jm-chart-y-axis__label" style="bottom: ' . esc_attr( $position ) . '%;"><span>' . esc_html( number_format_i18n( $label ) ) . '</span></div>';
				}
				?>
			</div>
			<div class="jm-chart-bars">
				<?php
				$i     = 0;
				$count = count( $values );
				foreach ( $values as $day ) : ?>
					<?php
					$class   = $day['class'] ?? '';
					$percent = $i++ / $count * 100;
					if ( $percent > 80 ) {
						$class .= ' jm-chart-bar--right-edge';
					}
					?>
					<div class="jm-chart-bar <?php echo esc_attr( $class ); ?>"
						aria-describedby="jm-chart-bar-tooltip-<?php echo esc_attr( $day['date'] ); ?>">
						<div class="jm-chart-bar-tooltip jm-ui-tooltip"
							id="jm-chart-bar-tooltip-<?php echo esc_attr( $day['date'] ); ?>">
							<div class="jm-ui-row">
								<strong><?php echo esc_html( $day['date'] ); ?></strong>
							</div>
							<div class="jm-ui-row">
								<?php esc_html_e( 'Search impressions', 'wp-job-manager' ); ?>
								<strong><?php echo esc_html( number_format_i18n( $day['impressions'] ) ); ?></strong>
							</div>
							<div class="jm-ui-row">
								<?php esc_html_e( 'Page views', 'wp-job-manager' ); ?>
								<strong><?php echo esc_html( number_format_i18n( $day['views'] ) ); ?></strong>
							</div>
							<div class="jm-ui-row">
								<?php esc_html_e( 'Unique visitors', 'wp-job-manager' ); ?>
								<strong><?php echo esc_html( number_format_i18n( $day['uniques'] ) ); ?></strong>
							</div>


						</div>
						<div class="jm-chart-bar-value"
							style="height: <?php echo esc_attr( ( $day['views'] / $chart['max'] ) * 100 ); ?>%;"></div>
						<div class="jm-chart-bar-inner-value"
							style="height: <?php echo esc_attr( ( $day['uniques'] / $chart['max'] ) * 100 ); ?>%;"></div>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="jm-chart-x-axis">
				<div class="jm-chart-x-axis__label">
					<?php echo esc_html( array_key_first( $values ) ); ?>
				</div>
				<div class="jm-chart-x-axis__label">
					<?php echo esc_html( array_key_last( $values ) ); ?>
				</div>
			</div>
		</div>
	</div>
	<div class="jm-job-stat-details jm-ui-row">
		<?php foreach ( $stats as $column_name => $column ) : ?>
			<div class="jm-ui-col">
				<?php foreach ( $column as $i => $section ) :
					$help_text = $section['help'] ?? '';
					$tooltip_id = $help_text ? 'jm-stat-section-tooltip-' . $column_name . '-' . $i : '';
					?>
					<div class="jm-stat-section">
						<div class="jm-section-header" aria-describedby="<?php echo esc_attr( $tooltip_id ); ?>">
							<span><?php echo esc_html( $section['title'] ); ?></span>
							<?php if ( ! empty( $help_text ) ): ?>
								<span class="jm-section-header__help jm-ui-has-tooltip" tabindex="0">
									<?php echo UI_Elements::icon( 'help' ); ?>
									<div role="tooltip" class="jm-ui-tooltip" id="<?php echo esc_attr( $tooltip_id ); ?>">
										<?php echo esc_html( $help_text ); ?>
									</div>
								</span>
							<?php endif; ?>
						</div>
						<?php foreach ( $section['stats'] as $stat ) : ?>
							<div class="jm-stat-row jm-ui-row">
								<?php if ( isset( $stat['icon'] ) ) {
									echo UI_Elements::icon( $stat['icon'], $stat['label'] );
								} ?>
								<div class="jm-stat-label">
									<?php echo esc_html( $stat['label'] ); ?>
								</div>
								<div class="jm-stat-value">
									<?php if ( isset( $stat['value'] ) ) : ?>
										<?php echo esc_html( number_format_i18n( $stat['value'] ) ); ?>
									<?php endif; ?>
									<?php if ( isset( $stat['percent'] ) ) : ?>
										<span
											class="jm-stat-value-percent"><?php echo esc_html( number_format_i18n( $stat['percent'], 2 ) ); ?>%</span>
									<?php endif; ?>
								</div>
								<?php if ( isset( $stat['background'] ) ) : ?>
									<span
										class="jm-stat-background"
										style="width: <?php echo esc_attr( $stat['background'] . '%' ); ?>;"></span>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>

	</div>
</div>
