<?php
namespace PublicUHC\MinecraftAuth\Server;

use PublicUHC\MinecraftAuth\Protocol\HandshakePacket;
use PublicUHC\MinecraftAuth\Protocol\Constants\Stage;
use PublicUHC\MinecraftAuth\Protocol\StatusResponsePacket;
use PublicUHC\MinecraftAuth\Server\DataTypes\VarInt;

class Client {

    private $connection;
    private $stage;

    /**
     * Create a new Client
     *
     * @param $resource resource the client connection
     */
    public function __construct($resource)
    {
        $this->connection = $resource;
        $this->stage = Stage::HANDSHAKE();
    }

    /**
     * Get the connection handle
     *
     * @return resource the socket connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Closes the socket
     */
    public function close()
    {
        @fclose($this->connection);
        $this->connection = null;
    }

    /**
     * @return bool if the connection is opened
     */
    public function isOpen()
    {
        return $this->connection != null;
    }

    /**
     * @return int the stage, from PublicUHC\Server\Constants\Stage
     */
    public function getStage()
    {
        return $this->stage;
    }

    /**
     * Read a packet from the connection
     *
     * @throws NoDataException if there is no data supplied and closes the connection
     * @throws InvalidDataException if data didn't match the expected
     */
    public function readPacket()
    {
        //packet length - VarInt - length of the data + packetID
        $packetLength = VarInt::readUnsignedVarInt($this->connection);
        echo "-> Packet bytes: {$packetLength->getValue()}\n";

        //packet ID - the ID of the packet, relevant to each stage?
        $packetIDInt = VarInt::readUnsignedVarInt($this->connection);
        $packetID = $packetIDInt->getValue();
        echo '-> Packet ID: 0x'. dechex($packetID) ."\n";

        switch($this->stage) {
            case Stage::HANDSHAKE():
                switch($packetID) {
                    case 0:
                        echo "-> HANDSHAKE - HANDSHAKE\n";
                        $handshake = HandshakePacket::fromStream($this->connection);

                        //var_dump($handshake);

                        //switch to the requested stage
                        $this->stage = $handshake->getNextStage();
                        echo 'Switching to stage: ' . $this->stage->getName() . "\n";
                        break;
                    default:
                        throw new InvalidDataException("$packetID is not a valid packet in this stage (HANDSHAKE)");
                }
                break;
            case Stage::LOGIN():
                switch($packetID) {
                    default:
                        throw new InvalidDataException("$packetID is not a valid packet in this stage (LOGIN)");
                }
                break;
            case Stage::STATUS():
                switch($packetID) {
                    case 0:
                        echo "-> STATUS - REQUEST\n";
                        //request packet
                        $response = new StatusResponsePacket();
                        $response->setDescription('Test Server')
                            ->setMaxPlayers(10)
                            ->setOnlineCount(0)
                            ->setProtocol(5)
                            ->setVersion('1.7.9');

                        $encoded = $response->encode();
                        if(strlen($encoded) != fwrite($this->connection, $encoded, strlen($encoded))) {
                            throw new InvalidDataException('Cannot write packet');
                        }
                        break;
                    case 1:
                        echo "-> STATUS - PING\n";
                        $this->close();
                        break;
                    default:
                        throw new InvalidDataException("$packetID is not a valid packet in this stage (STATUS)");
                }
                break;
            default:
                throw new InvalidDataException('Not in a valid stage');
        }
    }

    /**
     * helper function TODO remove
     * @param $length
     */
    private function printContents($length)
    {
        $data = @fread($this->connection, $length);
        echo $data . "\n";
    }
} 