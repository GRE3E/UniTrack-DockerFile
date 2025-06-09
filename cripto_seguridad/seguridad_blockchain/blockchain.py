from web3 import Web3
from eth_utils import to_bytes

ABI_CONTRATO = [
    {
        "inputs": [
            {
                "internalType": "bytes32",
                "name": "hash",
                "type": "bytes32"
            }
        ],
        "name": "guardarHash",
        "outputs": [],
        "stateMutability": "nonpayable",
        "type": "function"
    },
    {
        "anonymous": False,
        "inputs": [
            {
                "indexed": True,
                "internalType": "address",
                "name": "quien",
                "type": "address"
            },
            {
                "indexed": True,
                "internalType": "bytes32",
                "name": "hash",
                "type": "bytes32"
            }
        ],
        "name": "HashGuardado",
        "type": "event"
    },
    {
        "inputs": [
            {
                "internalType": "address",
                "name": "quien",
                "type": "address"
            }
        ],
        "name": "obtenerHash",
        "outputs": [
            {
                "internalType": "bytes32",
                "name": "",
                "type": "bytes32"
            }
        ],
        "stateMutability": "view",
        "type": "function"
    }
]

def enviar_a_blockchain(hash_str):
    ganache_url = 'http://127.0.0.1:7545'
    web3 = Web3(Web3.HTTPProvider(ganache_url))

    if not web3.is_connected():
        print("Error: No se pudo conectar a Ganache.")
        return False


    contrato_direccion = '0x4e3FC8f5EBaE8322f7ceD018535F11B7215835C2'

    try:
        contrato = web3.eth.contract(address=contrato_direccion, abi=ABI_CONTRATO)

    except ValueError as e:
        print(f"Error: No se pudo cargar el contrato. Detalles: {e}")
        return False

    try:
        hash_bytes32 = to_bytes(hexstr=hash_str)
        if len(hash_bytes32) != 32:
            raise ValueError("El hash no es de 32 bytes")

    except Exception as e:
        print(f"Error al convertir el hash: {e}")
        return False

    try:
        cuentas = web3.eth.accounts
        if not cuentas:
            print("Error: No hay cuentas disponibles en Ganache.")
            return False
        cuenta = cuentas[0]

        transaccion = contrato.functions.guardarHash(hash_bytes32).transact({'from': cuenta})
        receipt = web3.eth.wait_for_transaction_receipt(transaccion)

        if receipt.status == 1:


            return True, hash_str

        else:
            print("Error: La transacción fue ejecutada pero falló al ser confirmada.")
            return False
    except Exception as e:
        print(f"Error al enviar el hash a la blockchain: {e}")
        return False

"""def obtener_hash_de_blockchain(direccion):
    if not Web3.is_connected():
        print("Error: No se pudo conectar a Ganache.")
        return None
    else:
        print("Conexión a Ganache exitosa.")

    contrato_direccion = '0x4e3FC8f5EBaE8322f7ceD018535F11B7215835C2'

    try:
        contrato = Web3.eth.contract(address=contrato_direccion, abi=ABI_CONTRATO)
        print("Contrato cargado exitosamente.")
    except ValueError as e:
        print(f"Error: No se pudo cargar el contrato. Detalles: {e}")
        return None

    try:
        hash_almacenado = contrato.functions.obtenerHash(direccion).call()
        print(f"Hash almacenado en la blockchain: {hash_almacenado.hex()}")
        return hash_almacenado.hex()
    except Exception as e:
        print(f"Error al obtener el hash de la blockchain: {e}")
        return None
"""

