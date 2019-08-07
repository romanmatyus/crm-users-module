<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\UsersModule\Repository\AddressChangeRequestsRepository;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\CountriesRepository;
use Nette\Http\Response;

class CreateAddressChangeRequestHandler extends ApiHandler
{
    private $addressChangeRequestsRepository;

    private $userManager;

    private $addressesRepository;

    private $countriesRepository;

    public function __construct(
        AddressChangeRequestsRepository $addressChangeRequestsRepository,
        UserManager $userManager,
        AddressesRepository $addressesRepository,
        CountriesRepository $countriesRepository
    ) {
        $this->addressChangeRequestsRepository = $addressChangeRequestsRepository;
        $this->userManager = $userManager;
        $this->addressesRepository = $addressesRepository;
        $this->countriesRepository = $countriesRepository;
    }

    public function params()
    {
        return [
            new InputParam(InputParam::TYPE_POST, 'email', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_POST, 'type', InputParam::REQUIRED),

            new InputParam(InputParam::TYPE_POST, 'first_name', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'last_name', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'company_name', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'address', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'number', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'zip', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'city', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'country_id', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'phone_number', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'company_id', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'company_tax_id', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'company_vat_id', InputParam::OPTIONAL),
        ];
    }

    public function handle(ApiAuthorizationInterface $authorization)
    {
        $paramsProcessor = new ParamsProcessor($this->params());
        $params = $paramsProcessor->getValues();

        $user = $this->userManager->loadUserByEmail($params['email']);

        $parentAddress = $this->addressesRepository->address($user, $params['type']);
        if (!$parentAddress) {
            $response = new JsonResponse([
                'status' => 'errror',
                'message' => 'Parent address not found',
            ]);
            $response->setHttpCode(Response::S404_NOT_FOUND);
            return $response;
        }

        $change = $this->addressChangeRequestsRepository->add(
            $user,
            $parentAddress,
            $params['first_name'],
            $params['last_name'],
            $params['company_name'],
            $params['address'],
            $params['number'],
            $params['city'],
            $params['zip'],
            $params['country_id'] ?? $this->countriesRepository->defaultCountry()->id,
            $params['company_id'],
            $params['company_tax_id'],
            $params['company_vat_id'],
            $params['phone_number'],
            $params['type']
        );

        $response = new JsonResponse([
            'status' => 'ok',
            'address' => [
                'id' => $change->id,
            ],
        ]);
        $response->setHttpCode(Response::S200_OK);
        return $response;
    }
}
