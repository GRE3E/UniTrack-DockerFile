from web3 import Web3

def test_connection():
    ganache_url = 'http://127.0.0.1:7545'
    web3 = Web3(Web3.HTTPProvider(ganache_url))

    if web3.is_connected():
        print("Conexión a Ganache exitosa.")
        print(f"Versión de Ethereum: {web3.eth.protocol_version}")
        print(f"Bloques actuales: {web3.eth.block_number}")
        print(f"Cuentas disponibles: {web3.eth.accounts}")
    else:
        print("Error: No se pudo conectar a Ganache.")

if __name__ == "_main_":
    test_connection()