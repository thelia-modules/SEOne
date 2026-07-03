<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SEOne\Controller;

use SEOne\Model\RobotsQuery;
use SEOne\SEOne;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Translation\Translator;

class RobotTxtController extends BaseFrontController
{
    #[Route('/robots.txt', name: 'robot_txt', methods: 'GET')]
    public function showRobotTxt(Request $request): Response
    {
        $domain = $request->getHttpHost();

        $robot = RobotsQuery::create()->findOneByDomainName('http://'.$domain);
        if ($robot === null) {
            $robot = RobotsQuery::create()->findOneByDomainName('https://'.$domain);
        }
        if ($robot === null) {
            $robot = RobotsQuery::create()->findOneByDomainName($domain);
        }
        if ($robot === null) {
            throw new \RuntimeException(Translator::getInstance()->trans(
                'No robot.txt found for this domain name. Check your module in your backoffice.',
                [],
                SEOne::DOMAIN_NAME
            ));
        }

        return new Response($robot->getRobotsContent(), 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
