<?php

namespace Crm\UsersModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\ApiModule\Response\ApiResponseInterface;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Events\NewAddressEvent;
use Crm\UsersModule\Repository\AddressChangeRequestsRepository;
use Crm\UsersModule\Repository\AddressTypesRepository;
use Crm\UsersModule\Repository\CountriesRepository;
use League\Event\Emitter;
use Nette\Http\Response;

class CreateAddressHandler extends ApiHandler
{
    private $userManager;

    private $addressChangeRequestsRepository;

    private $addressTypesRepository;

    private $countriesRepository;

    private $emitter;

    public function __construct(
        UserManager $userManager,
        AddressChangeRequestsRepository $addressChangeRequestsRepository,
        AddressTypesRepository $addressTypesRepository,
        CountriesRepository $countriesRepository,
        Emitter $emitter
    ) {
        $this->userManager = $userManager;
        $this->addressChangeRequestsRepository = $addressChangeRequestsRepository;
        $this->addressTypesRepository = $addressTypesRepository;
        $this->countriesRepository = $countriesRepository;
        $this->emitter = $emitter;
    }

    public function params(): array
    {
        return [
            new InputParam(InputParam::TYPE_POST, 'email', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_POST, 'type', InputParam::REQUIRED),

            new InputParam(InputParam::TYPE_POST, 'first_name', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'last_name', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'address', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'number', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'zip', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'city', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'country_iso', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'company_name', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'company_id', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'tax_id', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'vat_id', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'phone_number', InputParam::OPTIONAL),
        ];
    }

    public function handle(array $params): ApiResponseInterface
    {
        $paramsProcessor = new ParamsProcessor($this->params());

        $error = $paramsProcessor->hasError();
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
        if (isset($params['country_iso']) && !$country) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'Country not found']);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }

        $changeRequest = $this->addressChangeRequestsRepository->add(
            $user,
            null,
            $params['first_name'],
            $params['last_name'],
            $params['company_name'],
            $params['address'],
            $params['number'],
            $params['city'],
            $params['zip'],
            $country->id ?? $this->countriesRepository->defaultCountry()->id,
            $params['company_id'],
            $params['tax_id'],
            $params['vat_id'],
            $params['phone_number'],
            $params['type']
        );
        $address = $this->addressChangeRequestsRepository->acceptRequest($changeRequest);

        if ($address) {
            $this->emitter->emit(new NewAddressEvent($address));
            $result = [
                'status' => 'ok',
                'address' => [
                    'id' => $address->id,
                ],
            ];
            $response = new JsonResponse($result);
            $response->setHttpCode(Response::S200_OK);
        } else {
            $result = [
                'status' => 'error',
                'message' => 'Cannot create address',
            ];
            $response = new JsonResponse($result);
            $response->setHttpCode(Response::S500_INTERNAL_SERVER_ERROR);
        }

        return $response;
    }
}
