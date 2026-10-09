<?php
namespace Aasc\Audit\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Error;
use Bitrix\Main\UserTable;
use Bitrix\Main\GroupTable;

class Auth extends Controller
{
    public function configureActions(): array
    {
        return [
            'login' => [
                'prefilters' => [
                    new ActionFilter\Csrf(),
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
                ],
                '-prefilters' => [
                    ActionFilter\Authentication::class,
                ],
            ],
            'register' => [
                'prefilters' => [
                    new ActionFilter\Csrf(),
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
                ],
                '-prefilters' => [
                    ActionFilter\Authentication::class,
                ],
            ],
            'logout' => [
                'prefilters' => [
                    new ActionFilter\HttpMethod([
                        ActionFilter\HttpMethod::METHOD_POST,
                        ActionFilter\HttpMethod::METHOD_GET
                    ]),
                ],
            ],
            'status' => [
                'prefilters' => [
                    new ActionFilter\HttpMethod([
                        ActionFilter\HttpMethod::METHOD_GET,
                        ActionFilter\HttpMethod::METHOD_POST
                    ]),
                ],
                '-prefilters' => [
                    ActionFilter\Authentication::class,
                ],
            ],
        ];
    }

    /**
     * Action đăng nhập người dùng vào cổng thông tin
     * URL: /bitrix/services/main/ajax.php?action=aasc:audit.controller.auth.login
     */
    public function loginAction(string $login = '', string $password = '', string $remember = 'Y'): ?array
    {
        if (empty($login)) {
            $post = $this->getRequest()->getPostList();
            $login = (string)$post->get('login');
            $password = (string)$post->get('password');
            $remember = (string)($post->get('remember') ?: 'Y');
        }

        $login = trim($login);
        $password = trim($password);

        if (empty($login) || empty($password)) {
            $this->addError(new Error('Vui lòng nhập tên đăng nhập hoặc email cùng mật khẩu.'));
            return null;
        }

        global $USER;
        if (!is_object($USER)) {
            $USER = new \CUser();
        }

        $authResult = $USER->Login($login, $password, $remember === 'Y' ? 'Y' : 'N');

        if ($authResult === true || (is_array($authResult) && ($authResult['TYPE'] ?? '') === 'OK')) {
            return [
                'status'   => 'success',
                'userId'   => (int)$USER->GetID(),
                'login'    => $USER->GetLogin(),
                'fullName' => $USER->GetFullName() ?: $USER->GetLogin(),
                'email'    => $USER->GetEmail(),
                'message'  => 'Đăng nhập thành công.',
            ];
        }

        $errorMessage = 'Tên đăng nhập hoặc mật khẩu không chính xác.';
        if (is_array($authResult) && !empty($authResult['MESSAGE'])) {
            $errorMessage = strip_tags($authResult['MESSAGE']);
        }

        $this->addError(new Error($errorMessage));
        return null;
    }

    /**
     * Action đăng ký tài khoản khách hàng cổng thông tin
     * URL: /bitrix/services/main/ajax.php?action=aasc:audit.controller.auth.register
     */
    public function registerAction(
        string $name = '',
        string $email = '',
        string $phone = '',
        string $password = '',
        string $confirmPassword = '',
        string $companyName = '',
        string $taxCode = ''
    ): ?array {
        if (empty($email)) {
            $post = $this->getRequest()->getPostList();
            $name = (string)$post->get('name');
            $email = (string)$post->get('email');
            $phone = (string)$post->get('phone');
            $password = (string)$post->get('password');
            $confirmPassword = (string)$post->get('confirmPassword');
            $companyName = (string)$post->get('companyName');
            $taxCode = (string)$post->get('taxCode');
        }

        $name = trim($name);
        $email = trim(strtolower($email));
        $phone = trim($phone);
        $password = trim($password);
        $confirmPassword = trim($confirmPassword);
        $companyName = trim($companyName);
        $taxCode = trim($taxCode);

        if (empty($name)) {
            $this->addError(new Error('Vui lòng nhập họ và tên người liên hệ.'));
            return null;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addError(new Error('Địa chỉ email không đúng định dạng.'));
            return null;
        }

        if (strlen($password) < 6) {
            $this->addError(new Error('Mật khẩu phải chứa ít nhất 6 ký tự.'));
            return null;
        }

        if ($password !== $confirmPassword) {
            $this->addError(new Error('Mật khẩu xác nhận không trùng khớp.'));
            return null;
        }

        // Kiểm tra tài khoản đã tồn tại
        $existing = UserTable::getList([
            'filter' => [
                'LOGIC' => 'OR',
                ['=LOGIN' => $email],
                ['=EMAIL' => $email],
            ],
            'select' => ['ID'],
        ])->fetch();

        if ($existing) {
            $this->addError(new Error('Địa chỉ email này đã được sử dụng. Vui lòng đăng nhập hoặc dùng email khác.'));
            return null;
        }

        // Lấy nhóm Khách hàng Cổng thông tin (PORTAL_CLIENTS)
        $portalGroup = GroupTable::getList([
            'filter' => ['=STRING_ID' => 'PORTAL_CLIENTS'],
            'select' => ['ID'],
        ])->fetch();
        $portalGroupId = $portalGroup ? (int)$portalGroup['ID'] : 17;

        $userObj = new \CUser();
        $arFields = [
            'NAME'             => $name,
            'LAST_NAME'        => '',
            'EMAIL'            => $email,
            'LOGIN'            => $email,
            'LID'              => SITE_ID ?: 's1',
            'ACTIVE'           => 'Y',
            'GROUP_ID'         => [2, $portalGroupId],
            'PASSWORD'         => $password,
            'CONFIRM_PASSWORD' => $confirmPassword,
            'PERSONAL_PHONE'   => $phone,
            'WORK_COMPANY'     => $companyName,
            'WORK_PROFILE'     => $taxCode,
        ];

        $newUserId = $userObj->Add($arFields);

        if ($newUserId > 0) {
            global $USER;
            if (!is_object($USER)) {
                $USER = new \CUser();
            }
            $USER->Authorize($newUserId);

            return [
                'status'   => 'success',
                'userId'   => (int)$newUserId,
                'login'    => $email,
                'fullName' => $name,
                'message'  => 'Đăng ký tài khoản thành công.',
            ];
        }

        $this->addError(new Error($userObj->LAST_ERROR ?: 'Không thể khởi tạo tài khoản trên hệ thống.'));
        return null;
    }

    /**
     * Action đăng xuất
     * URL: /bitrix/services/main/ajax.php?action=aasc:audit.controller.auth.logout
     */
    public function logoutAction(): array
    {
        global $USER;
        if (is_object($USER)) {
            $USER->Logout();
        }

        return [
            'status'  => 'success',
            'message' => 'Đã đăng xuất khỏi cổng thông tin.',
        ];
    }

    /**
     * Action kiểm tra trạng thái đăng nhập
     * URL: /bitrix/services/main/ajax.php?action=aasc:audit.controller.auth.status
     */
    public function statusAction(): array
    {
        global $USER;
        $isAuth = is_object($USER) && $USER->IsAuthorized();

        return [
            'authorized' => $isAuth,
            'userId'     => $isAuth ? (int)$USER->GetID() : null,
            'login'      => $isAuth ? $USER->GetLogin() : null,
            'fullName'   => $isAuth ? ($USER->GetFullName() ?: $USER->GetLogin()) : null,
            'email'      => $isAuth ? $USER->GetEmail() : null,
        ];
    }
}
