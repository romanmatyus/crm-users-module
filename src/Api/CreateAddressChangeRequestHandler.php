<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\AddressChangeRequestsRepository;
use Crm\UsersModule\Repository\AddressTypesRepository;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\CountriesRepository;
use Nette\Http\Response;

class CreateAddressChangeRequestHandler extends ApiHandler
{
    private $addressChangeRequestsRepository;

    private $addressTypesRepository;

    private $userManager;

    private $addressesRepository;

    private $countriesRepository;

    public function __construct(
        AddressChangeRequestsRepository $addressChangeRequestsRepository,
        AddressTypesRepository $addressTypesRepository,
        UserManager $userManager,
        AddressesRepository $addressesRepository,
        CountriesRepository $countriesRepository
    ) {
        $this->addressChangeRequestsRepository = $addressChangeRequestsRepository;
        $this->addressTypesRepository = $addressTypesRepository;
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

            // **Deprecated** and will be removed. Replaced with `country_iso`.
            new InputParam(InputParam::TYPE_POST, 'country_id', InputParam::OPTIONAL),

            new InputParam(InputParam::TYPE_POST, 'country_iso', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'phone_number', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'company_id', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'company_tax_id', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'company_vat_id', InputParam::OPTIONAL),
        ];
    }

    public function handle(ApiAuthorizationInterface $authorization)
    {
        $paramsProcessor = new ParamsProcessor($this->params());

        $error = $paramsProcessor->isError();
        if ($error) {
            $response = new JsonResponse(['status' => 'error', 'message' => $error]);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }

        $params = $paramsProcessor->getValues();

        $user = $this->userManager->loadUserByEmail($params['email']);
        if (!$user) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'User not found']);
            $response->setHttpCode(Response::S404_NOT_FOUND);
            return $response;
        }

        $type = $this->addressTypesRepository->findByType($params['type']);
        if (!$type) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'Address type not found']);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }

        $country = $this->countriesRepository->findByIsoCode($params['country_iso']);
        if (!$country) {
            $country = null;
            if (isset($params['country_iso'])) {
                $response = new JsonResponse(['status' => 'error', 'message' => 'Country not found']);
                $response->setHttpCode(Response::S400_BAD_REQUEST);
                return $response;
            }
        }

        $parentAddress = $this->addressesRepository->address($user, $params['type']);
        if (!$parentAddress) {
            $response = new JsonResponse([
                'status' => 'error',
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
            $params['country_id'] ?? $country->id ?? $this->countriesRepository->defaultCountry()->id,
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
