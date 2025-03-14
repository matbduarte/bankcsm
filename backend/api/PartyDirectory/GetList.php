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
    //     "FepId": "string",
    //     "ExternalIdentification": {
    //         "TypeOfIdentificationCode": "string",
    //         "Identification": "string"
    //     }
    // }

    // Check if required fields are present
    if (isset($data['ExternalIdentification'])) {
        // Check if ExternalIdentification contains TypeOfIdentificationCode and Identification
        if (!isset($data['ExternalIdentification']['TypeOfIdentificationCode']) || !isset($data['ExternalIdentification']['Identification'])) {
            header("HTTP/1.0 400 Bad Request");
            return array(
                "messages" => array(
                    array(
                        "errorCode" => "400",
                        "type" => "Error",
                        "title" => "Missing Fields",
                        "detail" => "ExternalIdentification fields 'TypeOfIdentificationCode' or 'Identification' are missing.",
                        "instance" => ""
                    )
                )
            );
        }
        $code = $data['ExternalIdentification']['TypeOfIdentificationCode'];
        $value = $data['ExternalIdentification']['Identification'];
    } else if (!isset($data['FepId'])) {
        header("HTTP/1.0 400 Bad Request");
        return array(
            "messages" => array(
                array(
                    "errorCode" => "400",
                    "type" => "Error",
                    "title" => "Missing Fields",
                    "detail" => "Either 'ExternalIdentification' or 'FepId' is required.",
                    "instance" => ""
                )
            )
        );
    } else {
        $fepid = $data['FepId'];
    }

    // Query external_identification table based on code and value
    $query = "SELECT * FROM external_identification WHERE type_of_identification = ? AND identification = ?";
    $stmt = $GLOBALS['connection']->prepare($query);
    $stmt->bind_param("ss", $code, $value);
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
                    "detail" => "No external identification found for the provided code and value.",
                    "instance" => ""
                )
            )
        );
    }

    $externalIdentification = $result->fetch_assoc();
    $personId = $externalIdentification['person'];

    // Now, retrieve the person's data from the person table
    $personQuery = "SELECT * FROM person WHERE identification = ?";
    $personStmt = $GLOBALS['connection']->prepare($personQuery);
    $personStmt->bind_param("s", $personId);
    $personStmt->execute();
    $personResult = $personStmt->get_result();
    $personData = $personResult->fetch_assoc();

    if (!$personData) {
        header("HTTP/1.0 404 Not Found");
        return array(
            "messages" => array(
                array(
                    "errorCode" => "404",
                    "type" => "Error",
                    "title" => "Person Not Found",
                    "detail" => "Person data not found for the provided external identification.",
                    "instance" => ""
                )
            )
        );
    }

    // Retrieve additional data from other tables (phone_address, electronic_address, postal_address)
    $postalQuery = "SELECT * FROM postal_address WHERE person = ?";
    $postalStmt = $GLOBALS['connection']->prepare($postalQuery);
    $postalStmt->bind_param("s", $personId);
    $postalStmt->execute();
    $postalResult = $postalStmt->get_result();
    $postalAddresses = $postalResult->fetch_all(MYSQLI_ASSOC);

    $phoneQuery = "SELECT * FROM phone_address WHERE person = ?";
    $phoneStmt = $GLOBALS['connection']->prepare($phoneQuery);
    $phoneStmt->bind_param("s", $personId);
    $phoneStmt->execute();
    $phoneResult = $phoneStmt->get_result();
    $phoneAddresses = $phoneResult->fetch_all(MYSQLI_ASSOC);

    $emailQuery = "SELECT * FROM electronic_address WHERE person = ?";
    $emailStmt = $GLOBALS['connection']->prepare($emailQuery);
    $emailStmt->bind_param("s", $personId);
    $emailStmt->execute();
    $emailResult = $emailStmt->get_result();
    $emailAddresses = $emailResult->fetch_all(MYSQLI_ASSOC);

    // Assemble response data
    $response = array(
        "FECID" => $personData['identification'],
        "Status" => $personData['status'],
        "FinancialInstitution" => $personData['financial_institution'],
        "FiName" => $personData['financial_institution'],
        "Brand" => $personData['brand'],
        "BrandName" => $personData['brand'],
        "ExternalIdentification" => array(
            array(
                "TypeOfIdentificationCode" => $externalIdentification['type_of_identification'],
                "Identification" => $externalIdentification['identification']
            )
        ),
        "NamePrefix" => $personData['name_prefix'],
        "GivenName" => $personData['given_name'],
        "MiddleName" => $personData['middle_name'],
        "Surname" => $personData['surname'],
        "BirthDate" => $personData['birth_date'],
        "Language" => $personData['language'],
        "MemorableWord" => $personData['memorable_word'],
        "MemorableWordReminder" => $personData['memorable_word_reminder'],
        "PostalAddress" => $postalAddresses,
        "Phone" => $phoneAddresses,
        "ElectronicAddress" => $emailAddresses,
        "enabled" => true
    );

    return $response;
}
?>
