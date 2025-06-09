// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;

contract SecurityContract {
    event HashGuardado(address indexed quien, bytes32 indexed hash);

    mapping(address => bytes32) private hashes;

    function guardarHash(bytes32 hash) public {
        require(
            hash != bytes32(0),
            "0xBFAf0c6F1D3e5c334f42D78FF19265f0cd27C2E8"
        ); 

        hashes[msg.sender] = hash;

        emit HashGuardado(msg.sender, hash);
    }
    function estaVacio(bytes32 hash) private pure returns (bool) {
        for (uint256 i = 0; i < 32; i++) {
            if (hash[i] != 0) {
                return false;
            }
        }
        return true;
    }

    function obtenerHash(address quien) public view returns (bytes32) {
        return hashes[quien];
    }
}
