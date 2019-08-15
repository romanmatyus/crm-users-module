<?php

namespace Crm\UsersModule\Components;

use Crm\ApplicationModule\User\UserData;
use Crm\ApplicationModule\Widget\WidgetInterface;
use Crm\UsersModule\Auth\Access\AccessToken;
use Crm\UsersModule\Repository\AccessTokensRepository;
use League\Event\Emitter;
use Nette\Application\UI\Control;
use Nette\Localization\ITranslator;

class UserTokens extends Control implements WidgetInterface
{
    private $templateName = 'user_tokens.latte';

    public $accessTokensRepository;

    private $accessToken;

    private $userData;

    private $emitter;

    private $translator;

    public function __construct(
        AccessTokensRepository $accessTokensRepository,
        AccessToken $accessToken,
        Emitter $emitter,
        UserData $userData,
        ITranslator $translator
    ) {
        parent::__construct();
        $this->accessTokensRepository = $accessTokensRepository;
        $this->accessToken = $accessToken;
        $this->emitter = $emitter;
        $this->userData = $userData;
        $this->translator = $translator;
    }

    public function header($id = '')
    {
        $header = $this->translator->translate('users.component.user_tokens.header');
        if ($id) {
            $header .= ' <small>(' . $this->totalCount($id) . ')</small>';
        }
        return $header;
    }

    public function identifier()
    {
        return 'usertokens';
    }

    public function render($id)
    {
        $accessTokens = $this->accessTokensRepository->allUserTokens($id);
        $tokensArray = [];
        foreach ($accessTokens as $token) {
            $tokensArray[] = $token->token;
        }

        $userTokensData = $this->userData->getUserTokens($tokensArray);
        $firstValue = null;
        foreach ($userTokensData as $token => $value) {
            if ($value == null) {
                $this->template->userDataErrorMessage = "Data inconsitency - missing token  (#$token)";
                break;
            }
            if (!$firstValue) {
                $firstValue = $value;
                continue;
            }

            if ($value != $firstValue) {
                $this->template->userDataErrorMessage = "Data inconsitency - wrong value in token ($token)";
                break;
            }
        }

        $this->template->lastVersion = $this->accessToken->lastVersion();
        $this->template->totalAccessTokens = $accessTokens->count('*');
        $this->template->accessTokens = $accessTokens;
        $this->template->id = $id;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }

    public function handleRemoveAccessToken($token)
    {
        $tokenRow = $this->accessTokensRepository->findBy('token', $token);
        $this->accessTokensRepository->remove($token);
        $this->presenter->flashMessage('Prístupový kód bol zmazaný');
        $this->presenter->redirect('UsersAdmin:Show', $tokenRow->user_id);
    }

    public function handleRemoveAllAccessToken($userId)
    {
        $this->accessTokensRepository->removeAllUserTokens($userId);
        $this->presenter->flashMessage('Všetky prístupové kódy boli zmazané');
        $this->presenter->redirect('UsersAdmin:Show', $userId);
    }

    private $totalCount = null;

    private function totalCount($id)
    {
        if ($this->totalCount == null) {
            $accessTokens = $this->accessTokensRepository->allUserTokens($id);
            $this->totalCount = $accessTokens->count('*');
        }
        return $this->totalCount;
    }
}
