<?php

declare(strict_types=1);

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

namespace Ampache\Module\Application\PrivateMessage;

use Ampache\Config\ConfigContainerInterface;
use Ampache\Config\ConfigurationKeyEnum;
use Ampache\Module\Application\ApplicationActionInterface;
use Ampache\Module\Application\Exception\AccessDeniedException;
use Ampache\Module\Authorization\AccessLevelEnum;
use Ampache\Module\Authorization\GuiGatekeeperInterface;
use Ampache\Module\Util\UiInterface;
use Ampache\Repository\PrivateMessageRepositoryInterface;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ConfirmDeleteAction implements ApplicationActionInterface
{
    public const REQUEST_KEY = 'confirm_delete';

    private ConfigContainerInterface $configContainer;

    private UiInterface $ui;

    private PrivateMessageRepositoryInterface $privateMessageRepository;

    public function __construct(
        ConfigContainerInterface $configContainer,
        UiInterface $ui,
        PrivateMessageRepositoryInterface $privateMessageRepository
    ) {
        $this->configContainer          = $configContainer;
        $this->ui                       = $ui;
        $this->privateMessageRepository = $privateMessageRepository;
    }

    public function run(ServerRequestInterface $request, GuiGatekeeperInterface $gatekeeper): ?ResponseInterface
    {
        if (
            $gatekeeper->mayAccess(AccessLevelEnum::TYPE_INTERFACE, AccessLevelEnum::LEVEL_USER) === false ||
            $this->configContainer->isFeatureEnabled(ConfigurationKeyEnum::SOCIABLE) === false
        ) {
            throw new AccessDeniedException('Access Denied: sociable features are not enabled.');
        }
        if ($this->configContainer->isFeatureEnabled(ConfigurationKeyEnum::DEMO_MODE) === true) {
            return null;
        }

        $messageIds = array_map(
            'intval',
            explode(',', $request->getQueryParams()['msgs'] ?? [])
        );
        foreach ($messageIds as $messageId) {
            try {
                $message = $this->privateMessageRepository->getById($messageId);

                if ($message->getRecipientUserId() !== $gatekeeper->getUserId()) {
                    throw new Exception();
                }
                $this->privateMessageRepository->delete($messageId);
            } catch (Exception $e) {
                throw new AccessDeniedException(
                    sprintf('Unknown or unauthorized private message `%d`.', $messageId)
                );
            }
        }

        $this->ui->showHeader();
        $this->ui->showConfirmation(
            T_('No Problem'),
            T_('Messages have been deleted'),
            sprintf(
                '%s/browse.php?action=pvmsg',
                $this->configContainer->getWebPath()
            )
        );
        $this->ui->showQueryStats();
        $this->ui->showFooter();

        return null;
    }
}
