<?= "<?php\n" ?>

declare(strict_types=1);

<?= $this->phpdoc ?>
<?php if($this->addContentElement): ?>

use <?= $this->fullyquallifiedcontentelementclassname ?>;

/**
 * Content element
 */
<?php if($this->contentelementcategorytrans != ""): ?>
$GLOBALS['TL_LANG']['CTE']['<?= $this->contentelementcategory ?>'] = '<?= $this->contentelementcategorytrans ?>';
<?php endif; ?>
$GLOBALS['TL_LANG']['CTE'][<?= $this->contentelementclassname ?>::TYPE] = ['<?= $this->contentelementtrans_0 ?>', '<?= $this->contentelementtrans_1 ?>'];
<?php endif; ?>

/**
 * Miscelaneous
 */
//$GLOBALS['TL_LANG']['MSC'][''] = '';

/**
 * Errors
 */
//$GLOBALS['TL_LANG']['ERR'][''] = '';
