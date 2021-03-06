<?php
/**
 * This file is part of One.Platform
 *
 * @license Modified BSD
 * @see https://github.com/gplanchat/one.platform
 *
 * Copyright (c) 2009-2010, Grégory PLANCHAT <g.planchat at gmail.com>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 *     - Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *     - Redistributions in binary form must reproduce the above copyright notice,
 *       this list of conditions and the following disclaimer in the documentation
 *       and/or other materials provided with the distribution.
 *
 *     - Neither the name of Grégory PLANCHAT nor the names of its
 *       contributors may be used to endorse or promote products derived from this
 *       software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *                                --> NOTICE <--
 *  This file is part of the core development branch, changing its contents will
 * make you unable to use the automatic updates manager. Please refer to the
 * documentation for further information about customizing One.Platform.
 *
 */

/**
 * CMS Page administration controller
 *
 * @uses        One_Admin_Core_ControllerAbstract
 * @uses        Zend_Form
 *
 * @access      public
 * @author      gplanchat
 * @category    Cms
 * @package     One_Admin_Cms
 * @subpackage  One_Admin_Cms
 */
class One_Admin_Cms_PageController
    extends One_Admin_Core_Controller_FormGridAbstract
{
    public function indexAction()
    {
        $this->loadLayout('admin.grid');

        $this->_prepareGrid('cms-page', 'cms/page.collection', $this->_getParam('sort'));

        $container = $this->getLayout()
            ->getBlock('container')
            ->setTitle('CMS Pages')
        ;

        $this->renderLayout();
    }

    public function gridAjaxAction()
    {
        $collection = $this->app()
            ->getModel('cms/page.collection')
            ->setPage($this->_getParam('p'), $this->_getParam('n'));

        $this->getResponse()
            ->setHeader('Content-Type', 'application/json; encoding=UTF-8')
            ->setBody(Zend_Json::encode($collection->load()->toArray()))
        ;
    }

    public function editAction()
    {
        if ($this->getRequest()->isPost()){
            $this->_forward('edit-post');
            return;
        }

        $this->_buildEditForm();

        $entityModel = $this->app()
            ->getModel('cms/page')
            ->load($this->_getParam('id'))
        ;

        $formKey = uniqid();
        $this->app()
            ->getSingleton('admin.core/session')
            ->setFormKey($formKey);

        $this->_form->populate(array(
            'form_key' => $formKey,
            'general' => array(
                'title'   => $entityModel->getTitle(),
                'url-key' => $entityModel->getPath(),
                'websites' => $entityModel->getWebsiteId()
                ),
            'content' => array(
                'html' => $entityModel->getContent()
                ),
            'meta' => array(
                'description' => $entityModel->getMetaDescription(),
                'keywords'    => $entityModel->getMetaKeywords()
                ),
            'layout' => array(
                'updates' => $entityModel->getLayoutUpdates(),
                'active'  => $entityModel->getLayoutUpdatesActivation(),
                )
            ));

        $websites = $this->_form->getTab('general')
            ->getElement('websites')
            ->setMultiOptions($this->app()->getModel('core/website.collection')->load()->toHash('label'))
        ;

        $this->getLayout()
            ->getBlock('container')
            ->addButtonDuplicate()
            ->addButtonDelete()
            ->setTitle('CMS Page')
            ->setEntityLabel(sprintf('Edit CMS Page "%s"', $entityModel->getTitle()))
            ->headTitle(sprintf('Edit CMS Page "%s"', $entityModel->getTitle()))
        ;

        $url = $this->app()
            ->getRouter()
            ->assemble(array(
                'path'       => $this->_getParam('path'),
                'controller' => $this->_getParam('controller'),
                'action'     => 'edit-post'
                ));

        $this->_form->setAction($url);

        $this->renderLayout();
    }

    public function editPostAction()
    {
        $entityModel = $this->app()
            ->getModel('cms/page')
            ->load($this->_getParam('id'))
        ;

        $optionGroups = array(
            'general' => array(
                'title'    => array($entityModel, 'setTitle'),
                'url-key'  => array($entityModel, 'setUrlKey'),
                'websites' => array($entityModel, 'setWebsiteId')
                ),
            'content' => array(
                'html' => array($entityModel, 'setContent')
                ),
            'meta' => array(
                'description' => array($entityModel, 'setMetaDescription'),
                'keywords'    => array($entityModel, 'setMetaKeywords')
                ),
            'layout' => array(
                'active'  => array($entityModel, 'setLayoutActive'),
                'updates' => array($entityModel, 'setLayoutUpdates')
                )
            );

        $session = $this->app()
            ->getSingleton('admin.core/session');

        $request = $this->getRequest();

        if ($request->getPost('form_key') === $session->getFormKey()) {
            $session->addError('Invalid form data.');

            $this->_helper->redirector->gotoRoute(array(
                    'path'       => $this->_getParam('path'),
                    'controller' => $this->_getParam('controller'),
                    'action'     => 'index'
                    ), null, true);
            return;
        }

        foreach ($optionGroups as $groupName => $groupElements) {
            $groupData = $request->getPost($groupName);
            if (!is_array($groupElements) || empty($groupName) || !is_array($groupData)) {
                continue;
            }

            foreach ($groupElements as $element => $callback) {
                if (!isset($groupData[$element])) {
                    continue;
                }

                call_user_func($callback, $groupData[$element]);
            }
        }
        try {
            $entityModel->save();
            $session->addError('Page successfully updated.');
        } catch (One_Core_Exception $e) {
            $session->addError('Could not save page updates.');
        }

        $this->_helper->redirector->gotoRoute(array(
                'path'       => $this->_getParam('path'),
                'controller' => $this->_getParam('controller'),
                'action'     => 'index'
                ), null, true);
    }

    public function newAction()
    {
        $this->_buildEditForm();

        $container = $this->getLayout()
            ->getBlock('container')
            ->setTitle('CMS Page')
            ->setEntityLabel('Add a new CMS Page')
            ->headTitle('Add a new CMS Page')
        ;

        $websites = $this->_form->getTab('general')
            ->getElement('websites')
            ->setMultiOptions($this->app()->getModel('core/website.collection')->load()->toHash('label'))
        ;

        $url = $this->app()
            ->getRouter()
            ->assemble(array(
                'path'       => $this->_getParam('path'),
                'controller' => $this->_getParam('controller'),
                'action'     => 'new-post'
                ));

        $this->_form->setAction($url);

        $this->renderLayout();
    }

    public function newPostAction()
    {
        $entityModel = $this->app()
            ->getModel('cms/page')
        ;

        $optionGroups = array(
            'general' => array(
                'title'    => array($entityModel, 'setTitle'),
                'urlkey'  => array($entityModel, 'setPath'),
                'websites' => array($entityModel, 'setWebsiteId')
                ),
            'content' => array(
                'html' => array($entityModel, 'setContent')
                ),
            'meta' => array(
                'description' => array($entityModel, 'setMetaDescription'),
                'keywords'    => array($entityModel, 'setMetaKeywords')
                ),
            'layout' => array(
                'active'  => array($entityModel, 'setLayoutActive'),
                'updates' => array($entityModel, 'setLayoutUpdates')
                )
            );

        $session = $this->app()
            ->getSingleton('admin.core/session');

        $request = $this->getRequest();

        if ($request->getPost('form_key') === $session->getFormKey()) {
            $session->addError('Invalid form data.');

            $this->_helper->redirector->gotoRoute(array(
                    'path'       => $this->_getParam('path'),
                    'controller' => $this->_getParam('controller'),
                    'action'     => 'index'
                    ), null, true);
            return;
        }

        foreach ($optionGroups as $groupName => $groupElements) {
            $groupData = $request->getPost($groupName);
            if (!is_array($groupElements) || empty($groupName) || !is_array($groupData)) {
                continue;
            }

            foreach ($groupElements as $element => $callback) {
                if (!isset($groupData[$element])) {
                    continue;
                }

                call_user_func($callback, $groupData[$element]);
            }
        }

        try {
            $entityModel->save();
            $session->addError('Page successfully updated.');
        } catch (One_Core_Exception $e) {
            $session->addError('Could not save page updates.');
        }

        $this->_helper->redirector->gotoRoute(array(
                'path'       => $this->_getParam('path'),
                'controller' => $this->_getParam('controller'),
                'action'     => 'index'
                ), null, true);
    }

    public function deleteAction()
    {
        try {
            $entityModel = $this->app()
                ->getModel('cms/page')
                ->load($this->_getParam('id'))
                ->delete()
            ;

            $this->app()
                ->getModel('admin.core/session')
                ->addInfo('The page has been successfully deleted.')
            ;
        } catch (One_Core_Exception $e) {
            $this->app()
                ->getModel('admin.core/session')
                ->addError('An error occured while deleting the page.')
            ;
        }

        $url = $this->app()
            ->getRouter()
            ->assemble(array(
                'path'       => $this->_getParam('path'),
                'controller' => $this->_getParam('controller'),
                'action'     => 'index'
                ));

        $this->_redirect($url);
    }

    protected function _buildEditForm()
    {
        $this->_prepareForm();

        $url = $this->app()
            ->getRouter()
            ->assemble(array(
                'path'       => $this->_getParam('path'),
                'controller' => $this->_getParam('controller'),
                'action'     => 'edit-post'
                ));
        $this->_form->setAction($url);

        $this->addTab('cms-page-general', 'general', 'General');
        $this->addTab('cms-page-content', 'content', 'Content');
        $this->addTab('cms-page-meta', 'meta', 'Meta data');
        $this->addTab('cms-page-layout', 'layout', 'Layout updates');
    }
}