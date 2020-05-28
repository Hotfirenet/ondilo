<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
    include_file('desktop', '404', 'php');
    die();
}

?>
<form class="form-horizontal">
    <fieldset>
        <div class="form-group">
            <label class="col-lg-4 control-label">{{Statut}}</label>
            <div class="col-lg-2">
            <?php
                if( config::byKey( 'expires_in', 'ondilo', 0 ) <= time() ) {
                    echo '<a class="btn btn-default" href="' . ondilo::getAuthorizationCode() . '" id="bt_connect" target="_blank"><i class="fa fa-paper-plane" aria-hidden="true"></i> {{Se connecter}}</a>';
                } else {
                    echo '<span class="label label-success">'.__('Actif', __FILE__).'</span>';
                }
            ?>
            </div>
        </div>
        <?php if( config::byKey( 'expires_in', 'ondilo', 0 ) > time() ) : ?>
		<div class="form-group">
            <label class="col-lg-4 control-label" for="custom_widget">{{Activer le widget personnalisé}}</label>
            <div class="col-lg-8">
                <input id="custom_widget" type="checkbox" class="configKey form-control" data-l1key="custom_widget" />
            </div>
        </div>
        <?php endif; ?>
  </fieldset>
</form>