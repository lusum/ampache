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

namespace Ampache\Module\Application\Label;

use Ampache\Config\ConfigContainerInterface;
use Ampache\Config\ConfigurationKeyEnum;
use Ampache\Repository\Model\Catalog;
use Ampache\Repository\Model\Label;
use Ampache\Module\Application\ApplicationActionInterface;
use Ampache\Module\Application\Exception\AccessDeniedException;
use Ampache\Module\Authorization\GuiGatekeeperInterface;
use Ampache\Module\Label\Deletion\LabelDeleterInterface;
use Ampache\Module\Util\UiInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ConfirmDeleteAction implements ApplicationActionInterface
{
    public const REQUEST_KEY = 'confirm_delete';

    private ConfigContainerInterface $configContainer;

    private UiInterface $ui;

    private LabelDeleterInterface $labelDeleter;

    public function __construct(
        ConfigContainerInterface $configContainer,
        UiInterface $ui,
        LabelDeleterInterface $labelDeleter
    ) {
        $this->configContainer = $configContainer;
        $this->ui              = $ui;
        $this->labelDeleter    = $labelDeleter;
    }

    public function run(ServerRequestInterface $request, GuiGatekeeperInterface $gatekeeper): ?ResponseInterface
    {
        if ($this->configContainer->isFeatureEnabled(ConfigurationKeyEnum::DEMO_MODE)) {
            $this->ui->showHeader();
            $this->ui->showQueryStats();
            $this->ui->showFooter();

            return null;
        }

        $label = new Label($_REQUEST['label_id']);
        if (!Catalog::can_remove($label)) {
            throw new AccessDeniedException(
                sprintf('Unauthorized to remove the label `%s`', $label->id)
            );
        }

        $this->ui->showHeader();
        if ($this->labelDeleter->delete($label)) {
            $this->ui->showConfirmation(
                T_('No Problem'),
                T_('The Label has been deleted'),
                $this->configContainer->getWebPath()
            );
        } else {
            $this->ui->showConfirmation(
                T_('There Was a Problem'),
                T_('Unable to delete this Label.'),
                $this->configContainer->getWebPath()
            );
        }

        $this->ui->showQueryStats();
        $this->ui->showFooter();

        return null;
    }
}
