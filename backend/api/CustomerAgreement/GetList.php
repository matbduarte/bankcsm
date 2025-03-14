<?php
include("../connection.php");
include("../auth.php");

// Auth Start
$auth = new authObj();
$isAuthorized = $auth->authenticate();
if (!$isAuthorized) {
    header("HTTP/1.0 401 Unauthorized");
    exit;
}
// Auth End

$db = new dbObj();
$connection = $db->getConnstring();

$request_method = $_SERVER["REQUEST_METHOD"];
if ($request_method == 'POST') {
    $headers = getallheaders();
    
    // Normalize header keys to handle case insensitivity
    $normalizedHeaders = array_change_key_case($headers, CASE_LOWER);

    // Check if the 'fi-id' header exists in a case-insensitive way
    if (!isset($normalizedHeaders['fi-id'])) {
        header("HTTP/1.0 400 Bad Request");
        echo json_encode(array(
            "messages" => array(
                array(
                    "errorCode" => "400",
                    "type" => "Error",
                    "title" => "Missing Header",
                    "detail" => "The 'fi-id' header is required.",
                    "instance" => ""
                )
            )
        ));
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        header("HTTP/1.0 400 Bad Request");
        echo json_encode(array(
            "messages" => array(
                array(
                    "errorCode" => "400",
                    "type" => "Error",
                    "title" => "Invalid JSON",
                    "detail" => "The request body contains invalid JSON.",
                    "instance" => ""
                )
            )
        ));
        exit;
    }

    // Process the data and generate a response
    $response = processRequest($data, $normalizedHeaders['fi-id']);
    header('Content-Type: application/json');
    echo json_encode($response);
} else {
    header("HTTP/1.0 405 Method Not Allowed");
    echo json_encode(array(
        "messages" => array(
            array(
                "errorCode" => "405",
                "type" => "Error",
                "title" => "Method Not Allowed",
                "detail" => "Only POST method is allowed.",
                "instance" => ""
            )
        )
    ));
}

function processRequest($data, $fiId) {
    // Possible body of the request
    // {
    //     "Person": "string",
    //     "ExternalAccount": {
    //         "ClearingSystemIdentificationCode": "string",
    //         "AccountIdentification": "string",
    //         "ClearingSystemMemberIdentification": "string"
    //     }
    // }

    // Check if required fields are present
    if (isset($data['ExternalAccount'])) {
        // Check if ExternalAccount contains ClearingSystemIdentificationCode, AccountIdentification and ClearingSystemMemberIdentification
        if (!isset($data['ExternalAccount']['ClearingSystemIdentificationCode']) || !isset($data['ExternalAccount']['AccountIdentification']) || !isset($data['ExternalAccount']['ClearingSystemMemberIdentification'])) {
            header("HTTP/1.0 400 Bad Request");
            return array(
                "messages" => array(
                    array(
                        "errorCode" => "400",
                        "type" => "Error",
                        "title" => "Missing Fields",
                        "detail" => "Required fields are missing.",
                        "instance" => ""
                    )
                )
            );
        }
        $code = $data['ExternalAccount']['ClearingSystemIdentificationCode'];
        $value = $data['ExternalAccount']['AccountIdentification'];
        $member = $data['ExternalAccount']['ClearingSystemMemberIdentification'];
    } else if (!isset($data['Person'])) {
        header("HTTP/1.0 400 Bad Request");
        return array(
            "messages" => array(
                array(
                    "errorCode" => "400",
                    "type" => "Error",
                    "title" => "Missing Fields",
                    "detail" => "Either 'Person' or 'ExternalAccount' is required.",
                    "instance" => ""
                )
            )
        );
    } else {
        $person = $data['Person'];
    }

    $agreements = [];
    $externalAccountRows = [];
    $agreementInvolvementRows = [];

    if ($person) {
        // Query agreement_involvement table based on person
        $query = "SELECT * FROM agreement_involvement WHERE person = ?";
        $stmt = $GLOBALS['connection']->prepare($query);
        $stmt->bind_param("s", $person);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows == 0) {
            header("HTTP/1.0 404 Not Found");
            return array(
                "messages" => array(
                    array(
                        "errorCode" => "404",
                        "type" => "Error",
                        "title" => "Not Found",
                        "detail" => "No agreement found for the provided person.",
                        "instance" => ""
                    )
                )
            );
        }

        // Get all agreements that person has and save the rows of agreement_involvement table
        while ($row = $result->fetch_assoc()) {
            $agreements[] = $row['agreement'];
            $agreementInvolvementRows[] = $row;
        }

        // We must query the external_account table for each agreement we found
        foreach ($agreements as $agreementId) {
            $query = "SELECT * FROM external_account WHERE agreement = ?";
            $stmt = $GLOBALS['connection']->prepare($query);
            $stmt->bind_param("s", $agreementId);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $externalAccountRows[] = $row;
            }
        }
    } elseif ($code && $value && $member) {
        $query = "SELECT * FROM external_account WHERE clearing_system_identification_code = ? AND account_identification_code = ? AND clearing_system_member_identification = ?";
        $stmt = $GLOBALS['connection']->prepare($query);
        $stmt->bind_param("sss", $code, $value, $member);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows == 0) {
            header("HTTP/1.0 404 Not Found");
            return array(
                "messages" => array(
                    array(
                        "errorCode" => "404",
                        "type" => "Error",
                        "title" => "Not Found",
                        "detail" => "No agreement found for the provided external account.",
                        "instance" => ""
                    )
                )
            );
        }

        $externalAccount = $result->fetch_assoc();
        if ($externalAccount) {
            $agreements[] = $externalAccount['agreement'];
            $externalAccountRows[] = $externalAccount;

            $query = "SELECT * FROM agreement_involvement WHERE agreement = ?";
            $stmt = $GLOBALS['connection']->prepare($query);
            $stmt->bind_param("s", $externalAccount['agreement']);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $agreementInvolvementRows[] = $row;
            }
        }
    }

    // Now, retrieve the agreement's data from the agreement table for each agreement found
    $agreementRows = [];
    foreach ($agreements as $agreementId) {
        $query = "SELECT * FROM agreement WHERE identification = ?";
        $stmt = $GLOBALS['connection']->prepare($query);
        $stmt->bind_param("s", $agreementId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows == 0) {
            header("HTTP/1.0 404 Not Found");
            return array(
                "messages" => array(
                    array(
                        "errorCode" => "404",
                        "type" => "Error",
                        "title" => "Agreement Not Found",
                        "detail" => "Agreement data not found for the provided input.",
                        "instance" => ""
                    )
                )
            );
        }

        while ($row = $result->fetch_assoc()) {
            $agreementRows[] = $row;
        }
    }

    // Now we have:
    // "agreementRows" => $agreementRows,
    // "agreementInvolvementRows" => $agreementInvolvementRows,
    // "externalAccountRows" => $externalAccountRows

    // Assemble response data
    $response = array();
    foreach ($agreementRows as $agreement) {
        // Filtrar os dados relacionados ao agreement atual
        $agreementId = $agreement['identification'];

        $filteredAgreementInvolvementRows = array_filter($agreementInvolvementRows, function ($row) use ($agreementId) {
            return $row['agreement'] === $agreementId;
        });

        $filteredExternalAccountRows = array_filter($externalAccountRows, function ($row) use ($agreementId) {
            return $row['agreement'] === $agreementId;
        });

        // Montar os dados do AgreementInvolvement
        $agreementInvolvements = array_map(function ($row) {
            return array(
                "AgreementInvolvementId" => $row['id'],
                "PartyIdentification" => $row['person'],
                "PartyRole" => $row['party_role'],
                "IsActive" => $row['is_active']
            );
        }, $filteredAgreementInvolvementRows);

        // Montar os dados do ExternalAccount
        $externalAccounts = array_map(function ($row) {
            return array(
                "ClearingSystemIdentificationCode" => $row['clearing_system_identification_code'],
                "AccountIdentification" => $row['account_identification_code'],
                "ClearingSystemMemberIdentification" => $row['clearing_system_member_identification']
            );
        }, $filteredExternalAccountRows);

        // Montar o objeto de resposta para o agreement atual
        $response[] = array(
            "Identification" => $agreement['identification'],
            "FinancialInstitution" => $agreement['financial_institution'],
            "Brand" => $agreement['brand'],
            "Product" => $agreement['product'],
            "Status" => $agreement['status'],
            "AgreementInvolvement" => $agreementInvolvements,
            "OpeningDate" => $agreement['opening_date'],
            "ExternalAccount" => $externalAccounts,
            "NetworkAccountUpdaterOption" => $agreement['is_network_account_updater_opted_out'],
            "BlockedReasonCode" => array(
                $agreement['blocked_reason_code']
            ),
            "CreditLimit" => array(
                "Value" => $agreement['credit_limit_amount'],
                "Currency" => $agreement['credit_limit_currency']
            ),
            "CashLimit" => array(
                "Value" => $agreement['cash_limit_amount'],
                "Currency" => $agreement['cash_limit_currency']
            ),
            "ClosureData" => array(
                "Closed" => $agreement['is_closed'],
                "ClosingDate" => $agreement['closing_date'],
                "ClosureRequestDate" => $agreement['closure_request_date'],
                "ClosedStatusReasonCode" => $agreement['closed_status_reason_code']
            ),
            "BalanceClearedDate" => $agreement['balance_cleared_date_time'],
            "BillOverlimitFeeNextCycle" => $agreement['bill_overlimit_fee_next_cycle'],
            "enabled" => true
        );
    }

    // Retornar a resposta montada
    header("HTTP/1.0 200 OK");
    return $response;
}
?>