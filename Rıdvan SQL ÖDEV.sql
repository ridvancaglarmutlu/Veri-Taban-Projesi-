create table siparisler(
SiparisID INT not NULL primary key,
SiparisTarihi DATE not NULL,
MusteriID INT,
odeme char(40),
KargoFirma varchar(50),
KargoTakipNO varchar(14),
TeslimDurumu varchar(30)
);


Create table musteriler(
MusteriID INT not Null primary key,
MusteriAD varchar(67) not NULL,
MusteriSoyad varchar(67) not NULL,
MusteriTelefon_1 varchar(23) not NULL,
MusteriTelefon_2 varchar(23) not NULL,
MusteriAdres varchar(200) not NULL
);

create table kitaplar(
KitapID INT not NULL primary key,
kitapadi varchar(30) not NULL,
YazarAdý varchar(40) not null,
Katogari varchar(15) NOT NULL,
BirimFiyat decimal(10,2) NOT NULL
);

create table siparisDetaylari(
DetayID INT primary key not null,
SiparisID INT,
KitapID varchar(34),
adet INT,
toplamtutar decimal(10,2),
kampanyaKodu varchar(29),
KampanyaÝndirim decimal(10,2),
YorumMetni varchar (150),
YorumPuani INT
);