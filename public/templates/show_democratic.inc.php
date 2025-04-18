<?php

declare(strict_types=0);

/**
 * vim:set softtabstop=4 shiftwidth=4 expandtab:
 *
 * LICENSE: GNU Affero General Public License, version 3 (AGPL-3.0-or-later)
 * Copyright Ampache.org, 2001-2023
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

use Ampache\Config\AmpConfig;
use Ampache\Module\Authorization\Access;
use Ampache\Module\Api\Ajax;
use Ampache\Module\Util\Ui;
use Ampache\Repository\Model\Democratic;

/** @var Democratic $democratic */

/* HINT: Democratic Playlist Name */
$string = $democratic->is_enabled() ? sprintf(T_('%s Playlist'), $democratic->name) : T_('Democratic Playlist');
Ui::show_box_top($string, 'info-box'); ?>
<div id="information_actions">
<ul>
<?php if ($democratic->is_enabled()) { ?>
<li>
    <?php echo T_('Cooldown'); ?>:<?php echo $democratic->f_cooldown; ?>
</li>
<?php } ?>
<?php if (Access::check('interface', 75)) { ?>
<li>
    <a href="<?php echo AmpConfig::get('web_path'); ?>/democratic.php?action=manage">
        <?php echo Ui::get_icon('server_lightning', T_('Configure Democratic Playlist')); ?>
        <?php echo T_('Configure Democratic Playlist'); ?>
    </a>
</li>
<?php if ($democratic->is_enabled()) { ?>
<li>
    <?php echo Ajax::button_with_text('?page=democratic&action=send_playlist&democratic_id=' . $democratic->id, 'all', T_('Play'), 'play_democratic'); ?>
</li>
<li>
    <?php echo Ajax::button_with_text('?page=democratic&action=clear_playlist&democratic_id=' . $democratic->id, 'delete', T_('Clear Playlist'), 'clear_democratic'); ?>
</li>
<?php } ?>
<?php } ?>
</ul>
</div>
<div style="text-align: right;">
    <script>
        function reloadPageChanged(obj)
        {
            if (obj.checked) {
                setTimeout(function() {
                    if (obj.checked) {
                        window.location.href = window.location.href<?php echo " + '&dummy=" . time() . "'";
if (!isset($_GET['reloadpage'])) {
    echo " + '&reloadpage=1'";
} ?>;
                    }
                }, <?php echo(AmpConfig::get('refresh_limit') * 1000); ?>);
            }
        }
        <?php if (isset($_GET['reloadpage'])) { ?>
        $(document).ready(function() {
            reloadPageChanged(document.getElementById('chkreloadpage'));
        });
        <?php } ?>
    </script>
    <input type="checkbox" id='chkreloadpage' onClick="reloadPageChanged(this);" <?php if (isset($_GET['reloadpage'])) {
        echo "checked";
    } ?> /> <?php echo T_('Reload this page automatically'); ?>
</div>
<?php Ui::show_box_bottom(); ?>
