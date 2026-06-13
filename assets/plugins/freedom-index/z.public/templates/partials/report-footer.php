<?php
if (!defined('ABSPATH')) exit;

/**
 * Report Footer Partial - Configurable for Scorecard vs Freedom Index
 * 
 * @param array $args {
 *     @type string $format Report format ('scorecard' or 'freedomindex')
 * }
 */

$format = $args['format'] ?? 'scorecard';
?>

<?php if ($format === 'scorecard'): ?>
    <!-- Scorecard Footer -->
    <!-- Footer content can be added here if needed -->
<?php else: ?>
    <!-- Freedom Index Footer -->
    <!-- Footer content can be added here if needed -->
<?php endif; ?>

