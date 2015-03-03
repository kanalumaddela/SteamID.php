<?php
/**
 * The SteamID library provides an easy way to work with SteamIDs and makes
 * conversions easy. Ported from SteamKit.
 *
 * This 64bit structure is used for identifying various objects on the Steam
 * network.
 *
 * This library requires GMP module to be installed.
 *
 * This implementation was ported from SteamKit:
 * {@link https://github.com/SteamRE/SteamKit/blob/master/SteamKit2/SteamKit2/Types/SteamID.cs}
 *
 * GitHub: {@link https://github.com/xPaw/SteamID.php}
 * Website: {@link http://xpaw.me}
 *
 * @author xPaw
 * @license LGPL
 * @version 0.0.1
 */
class SteamID
{
	/**
	 * @var array Types of steam account
	 */
	private static $AccountTypeChars = Array(
		self::TypeAnonGameServer => 'A',
		self::TypeGameServer     => 'G',
		self::TypeMultiseat      => 'M',
		self::TypePending        => 'P',
		self::TypeContentServer  => 'C',
		self::TypeClan           => 'g',
		self::TypeChat           => 'T', // Lobby chat is 'L', Clan chat is 'c'
		self::TypeInvalid        => 'I',
		self::TypeIndividual     => 'U',
		self::TypeAnonUser       => 'a',
	);
	
	/**
	 * Steam universes. Each universe is a self-contained Steam instance.
	 */
	const UniverseInvalid  = 0;
	const UniversePublic   = 1;
	const UniverseBeta     = 2;
	const UniverseInternal = 3;
	const UniverseDev      = 4;
	const UniverseRC       = 5;
	
	/**
	 * Steam account types.
	 */
	const TypeInvalid        = 0;
	const TypeIndividual     = 1;
	const TypeMultiseat      = 2;
	const TypeGameServer     = 3;
	const TypeAnonGameServer = 4;
	const TypePending        = 5;
	const TypeContentServer  = 6;
	const TypeClan           = 7;
	const TypeChat           = 8;
	const TypeP2PSuperSeeder = 9;
	const TypeAnonUser       = 10;
	
	/**
	 * Steam allows 3 simultaneous user account instances right now.
	 */
	const AllInstances    = 0;
	const DesktopInstance = 1;
	const ConsoleInstance = 2;
	const WebInstance     = 4;
	
	/**
	 * Special flags for Chat accounts - they go in the top 8 bits
	 * of the steam ID's "instance", leaving 12 for the actual instances
	 */
	const InstanceFlagClan     = 524288; // ( k_unSteamAccountInstanceMask + 1 ) >> 1
	const InstanceFlagLobby    = 262144; // ( k_unSteamAccountInstanceMask + 1 ) >> 2
	const InstanceFlagMMSLobby = 131072; // ( k_unSteamAccountInstanceMask + 1 ) >> 3
	
	/**
	 * @var resource
	 */
	private $Data;
	
	/**
	 * Initializes a new instance of the SteamID class.
	 *
	 * It automatically guesses which type the input is, and works from there.
	 *
	 * @param string|null $Value
	 * 
	 * @return SteamID Instance of SteamID class
	 */
	public function __construct( $Value = null )
	{
		$this->Data = gmp_init( 0 );
		
		if( $Value === null )
		{
			return;
		}
		
		// SetFromString
		if( preg_match( '/^STEAM_([0-5]):([0-1]):([0-9]{1,10})$/', $Value, $Matches ) === 1 )
		{
			$AccountID = $Matches[ 3 ];
			
			if( gmp_cmp( $AccountID, PHP_INT_MAX ) > 0 )
			{
				throw new InvalidArgumentException( 'Provided SteamID is invalid' );
			}
			
			$AuthServer = (int)$Matches[ 2 ];
			$AccountID = ( (int)$AccountID << 1 ) | $AuthServer;
			
			$this->SetAccountUniverse( self::UniversePublic );
			$this->SetAccountInstance( self::DesktopInstance );
			$this->SetAccountType( self::TypeIndividual );
			$this->SetAccountID( $AccountID );
		}
		// SetFromSteam3String
		else if( preg_match( '/^\\[([AGMPCgcLTIUai]):([0-5]):([0-9]{1,10})(:[0-9]+)?\\]$/', $Value, $Matches ) === 1 )
		{
			$AccountID = $Matches[ 3 ];
			
			if( gmp_cmp( $AccountID, PHP_INT_MAX ) > 0 )
			{
				throw new InvalidArgumentException( 'Provided SteamID is invalid' );
			}
			
			$Type = $Matches[ 1 ];
			
			if( isset( $Matches[ 4 ] ) )
			{
				$InstanceID = (int)ltrim( $Matches[ 4 ], ':' );
			}
			else if( $Type === 'U' )
			{
				$InstanceID = self::DesktopInstance;
			}
			else
			{
				$InstanceID = self::AllInstances;
			}
			
			if( $Type === 'c' )
			{
				$InstanceID |= self::InstanceFlagClan;
				
				$this->SetAccountType( self::TypeChat );
			}
			else if( $Type === 'L' )
			{
				$InstanceID |= self::InstanceFlagLobby;
				
				$this->SetAccountType( self::TypeChat );
			}
			else
			{
				$this->SetAccountType( array_search( $Type, self::$AccountTypeChars, true ) );
			}
			
			$this->SetAccountUniverse( (int)$Matches[ 2 ] );
			$this->SetAccountInstance( $InstanceID );
			$this->SetAccountID( (int)$AccountID );
		}
		else if( is_numeric( $Value ) && $Value >= 0 )
		{
			$this->Data = gmp_init( $Value );
		}
		else
		{
			throw new InvalidArgumentException( 'Provided SteamID is invalid' );
		}
	}
	
	/**
	 * Renders this instance into it's Steam2 "STEAM_" representation.
	 *
	 * @return string A string Steam2 "STEAM_" representation of this SteamID.
	 */
	public function RenderSteam2()
	{
		switch( $this->GetAccountType() )
		{
			case self::TypeInvalid:
			case self::TypeIndividual:
			{
				$Universe = $this->GetAccountUniverse();
				
				if( $Universe === self::UniversePublic )
				{
					// They're both STEAM_0
					$Universe = self::UniverseInvalid;
				}
				
				$AccountID = $this->GetAccountID();
				
				return 'STEAM_' . $Universe . ':' . ( $AccountID & 1 ) . ':' . 
					( $AccountID >> 1 );
			}
			default:
			{
				return $this->ConvertToUInt64();
			}
		}
	}
	
	/**
	 * Renders this instance into its Steam3 representation.
	 *
	 * @return string A string Steam3 representation of this SteamID.
	 */
	public function RenderSteam3()
	{
		$AccountInstance = $this->GetAccountInstance();
		$AccountType = $this->GetAccountType();
		$AccountTypeChar = isset( self::$AccountTypeChars[ $AccountType ] ) ? 
			self::$AccountTypeChars[ $AccountType ] : 
			'i';
		
		$RenderInstance = false;
		
		switch( $AccountType )
		{
			case self::TypeChat:
			{
				if( $AccountInstance & SteamID :: InstanceFlagClan )
				{
					$AccountTypeChar = 'c';
				}
				else if( $AccountInstance & SteamID :: InstanceFlagLobby )
				{
					$AccountTypeChar = 'L';
				}
				
				break;
			}
			case self::TypeAnonGameServer:
			case self::TypeMultiseat:
			{
				$RenderInstance = true;
				
				break;
			}
			case self::TypeIndividual:
			{
				$RenderInstance = $AccountInstance != self::DesktopInstance;
				
				break;
			}
		}
		
		$Return = '[' . $AccountTypeChar . ':' . $this->GetAccountUniverse() . 
			':' . $this->GetAccountID();
		
		if( $RenderInstance )
		{
			$Return .= ':' . $AccountInstance;
		}
		
		return $Return . ']';
	}
	
	/**
	 * Gets a value indicating whether this instance is valid.
	 *
	 * @return bool true if this instance is valid; otherwise, false.
	 */
	public function IsValid()
	{
		$AccountType = $this->GetAccountType();
		
		if( $AccountType <= self::TypeInvalid || $AccountType >= 11 ) // EAccountType.Max
		{
			return false;
		}
		
		$AccountUniverse = $this->GetAccountUniverse();
		
		// We don't care about non-public universes, but it should be
		// ( this.AccountUniverse <= EUniverse.Invalid || this.AccountUniverse >= EUniverse.Max )
		if( $AccountUniverse > self::UniversePublic )
		{
			return false;
		}
		
		$AccountID = $this->GetAccountID();
		$AccountInstance = $this->GetAccountInstance();
		
		if( $AccountType === self::TypeIndividual )
		{
			if( $AccountID == 0 || $AccountInstance > self::WebInstance )
			{
				return false;
			}
		}
		
		if( $AccountType === self::TypeClan )
		{
			if( $AccountID == 0 || $AccountInstance != 0 )
			{
				return false;
			}
		}
		
		if( $AccountType === self::TypeGameServer )
		{
			if( $AccountID == 0 )
			{
				return false;
			}
		}
		
		return true;
	}
	
	/**
	 * Sets the various components of this SteamID from a 64bit integer form.
	 *
	 * @param int $Value The 64bit integer to assign this SteamID from.
	 * 
	 * @throws InvalidArgumentException
	 */
	public function SetFromUInt64( $Value )
	{
		if( is_numeric( $Value ) )
		{
			$this->Data = gmp_init( $Value );
			
			return;
		}
		
		throw new InvalidArgumentException( 'Provided SteamID is numeric' );
	}
	
	/**
	 * Converts this SteamID into it's 64bit integer form. This function returns 
	 * as a string to work on 32-bit PHP systems.
	 *
	 * @return string A 64bit integer representing this SteamID.
	 */
	public function ConvertToUInt64()
	{
		return gmp_strval( $this->Data );
	}
	
	/**
	 * Gets the account id.
	 *
	 * @return int The account id.
	 */
	public function GetAccountID()
	{
		return gmp_intval( $this->Get( 0, '0xFFFFFFFF' ) );
	}
	
	/**
	 * Gets the account instance.
	 *
	 * @return int The account instance.
	 */
	public function GetAccountInstance()
	{
		return gmp_intval( $this->Get( 32, '0xFFFFF' ) );
	}
	
	/**
	 * Gets the account type.
	 *
	 * @return int The account type.
	 */
	public function GetAccountType()
	{
		return gmp_intval( $this->Get( 52, '0xF' ) );
	}
	
	/**
	 * Gets the account universe.
	 *
	 * @return int The account universe.
	 */
	public function GetAccountUniverse()
	{
		return gmp_intval( $this->Get( 56, '0xFF' ) );
	}
	
	/**
	 * Sets the account id.
	 *
	 * @param int $Value The account id.
	 * 
	 * @return SteamID Fluent interface
	 */
	public function SetAccountID( $Value )
	{
		$this->Set( 0, '0xFFFFFFFF', $Value );
		
		return $this;
	}
	
	/**
	 * Sets the account instance.
	 *
	 * @param int $Value The account instance.
	 * 
	 * @return SteamID Fluent interface
	 */
	public function SetAccountInstance( $Value )
	{
		$this->Set( 32, '0xFFFFF', $Value );
		
		return $this;
	}
	
	/**
	 * Sets the account type.
	 *
	 * @param int $Value The account type.
	 * 
	 * @return SteamID Fluent interface
	 */
	public function SetAccountType( $Value )
	{
		$this->Set( 52, '0xF', $Value );
		
		return $this;
	}
	
	/**
	 * Sets the account universe.
	 *
	 * @param int $Value The account universe.
	 * 
	 * @return SteamID Fluent interface
	 */
	public function SetAccountUniverse( $Value )
	{
		$this->Set( 56, '0xFF', $Value );
		
		return $this;
	}
	
	/**
	 * @param int $BitOffset
	 * @param int $ValueMask
	 * 
	 * @return resource
	 */
	private function Get( $BitOffset, $ValueMask )
	{
		return gmp_and( self::ShiftRight( $this->Data, $BitOffset ), $ValueMask );
	}
	
	/**
	 * @param int $BitOffset
	 * @param int $ValueMask
	 * @param int $Value
	 * 
	 * @return void
	 */
	private function Set( $BitOffset, $ValueMask, $Value )
	{
		$this->Data = gmp_or(
			gmp_and( $this->Data, gmp_com( self::ShiftLeft( $ValueMask, $BitOffset ) ) ),
			self::ShiftLeft( gmp_and( $Value, $ValueMask ), $BitOffset )
		);
	}
	
	/**
	 * Shift the bits of $x by $n steps to the left
	 * 
	 * @param int|resource $x
	 * @param int $n
	 *
	 * @return resource
	 */
	private static function ShiftLeft( $x, $n )
	{
		return gmp_mul( $x, gmp_pow( 2, $n ) );
	}
	
	/**
	 * Shift the bits of $x by $n steps to the right
	 * 
	 * @param int|resource $x
	 * @param int$n
	 *
	 * @return resource
	 */
	private static function ShiftRight( $x, $n )
	{
		return gmp_div( $x, gmp_pow( 2, $n ) );
	}
}
