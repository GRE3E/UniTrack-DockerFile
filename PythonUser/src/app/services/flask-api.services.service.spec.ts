import { TestBed } from '@angular/core/testing';

import { FlaskApiServicesService } from './flask-api.services.service';

describe('FlaskApiServicesService', () => {
  let service: FlaskApiServicesService;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(FlaskApiServicesService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
