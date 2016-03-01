<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2013 Daniel Garner
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version. 
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace Xibo\Controller;

use baseDAO;
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\TransitionFactory;
use Xibo\Helper\Form;


class Transition extends Base
{
    /**
     * No display page functionaility
     */
    function displayPage()
    {
        $this->getState()->template = 'transition-page';
    }

    public function grid()
    {
        $transitions = (new TransitionFactory($this->getContainer()))->query($this->gridRenderSort(), $this->gridRenderFilter());

        foreach ($transitions as $transition) {
            /* @var \Xibo\Entity\Transition $transition */

            // If the module config is not locked, present some buttons
            if ($this->getConfig()->GetSetting('TRANSITION_CONFIG_LOCKED_CHECKB') != 'Checked') {

                // Edit button
                $transition->buttons[] = array(
                    'id' => 'transition_button_edit',
                    'url' => $this->urlFor('transition.edit.form', ['id' => $transition->transitionId]),
                    'text' => __('Edit')
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = (new TransitionFactory($this->getContainer()))->countLast();
        $this->getState()->setData($transitions);
    }

    /**
     * Transition Edit Form
     * @param int $transitionId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function editForm($transitionId)
    {
        if ($this->getConfig()->GetSetting('TRANSITION_CONFIG_LOCKED_CHECKB') == 'Checked')
            throw new AccessDeniedException(__('Transition Config Locked'));

        $transition = (new TransitionFactory($this->getContainer()))->getById($transitionId);

        $this->getState()->template = 'transition-form-edit';
        $this->getState()->setData([
            'transition' => $transition,
            'help' => $this->getHelp()->link('Transition', 'Edit')
        ]);
    }

    /**
     * Edit Transition
     * @param int $transitionId
     */
    public function edit($transitionId)
    {
        if ($this->getConfig()->GetSetting('TRANSITION_CONFIG_LOCKED_CHECKB') == 'Checked')
            throw new AccessDeniedException(__('Transition Config Locked'));

        $transition = (new TransitionFactory($this->getContainer()))->getById($transitionId);
        $transition->availableAsIn = $this->getSanitizer()->getCheckbox('availableAsIn');
        $transition->availableAsOut = $this->getSanitizer()->getCheckbox('availableAsOut');
        $transition->save();

        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $transition->transition),
            'id' => $transition->transitionId,
            'data' => $transition
        ]);
    }
}
