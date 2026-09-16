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

namespace SEOne\Form;

use SEOne\SEOne;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

class EditRobotTxtForm extends BaseForm
{
    protected function buildForm(): void
    {
        $this->formBuilder->add(
            'id',
            IntegerType::class,
            [
                'constraints' => [
                    new NotBlank(message: Translator::getInstance()->trans('Robot id is required')),
                ],
            ]
        );

        $this->formBuilder->add(
            'robotContent',
            TextareaType::class,
            [
                'required' => false,
                'label' => Translator::getInstance()->trans(
                    'Robot Content',
                    [],
                    SEOne::DOMAIN_NAME
                ),
            ]
        );

        $this->formBuilder->add(
            'domainName',
            TextType::class,
            [
                'constraints' => [
                    new NotBlank(message: Translator::getInstance()->trans('Robot content is required')),
                ],
                'label' => Translator::getInstance()->trans(
                    'Domain Name',
                    [],
                    SEOne::DOMAIN_NAME
                ),
            ]);
    }

    public static function getName(): string
    {
        return 'robottxt_configuration';
    }
}
