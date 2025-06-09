import { Component, OnInit } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { FlaskApiService } from '../services/flask-api.services.service';
import { UserService } from '../services/user.service';
import { AlertController } from '@ionic/angular';
import { HttpClient } from '@angular/common/http';


@Component({
  selector: 'app-qr',
  templateUrl: './qr.page.html',
  styleUrls: ['./qr.page.scss']
})
export class QrPage implements OnInit {
  qrImageUrl: string = '';

  nombrecompleto: string = '';
 currentUser: any; 
  
   

  constructor(
    private route: ActivatedRoute,
    private flaskService: FlaskApiService,
    private user: UserService,
    private alertController: AlertController, 
   
    
  ) {}

  ngOnInit() {
    this.route.queryParams.subscribe(params => {
      this.qrImageUrl = params['qrImageUrl'];
      this.qrImageUrl += `?t=${new Date().getTime()}`;
      console.log('Received QR Image URL:', this.qrImageUrl)
      
    });

    this.currentUser = this.user.getCurrentUser();
    if (this.currentUser && this.currentUser.user && this.currentUser.user.nombres && this.currentUser.user.apellidos ) {
      this.nombrecompleto = `${this.currentUser.nombres} ${this.currentUser.apellidos}`;
      

    }
    
  }
  
  
}